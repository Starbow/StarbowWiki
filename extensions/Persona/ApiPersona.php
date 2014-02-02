<?php

/**
 * This file is part of the MediaWiki extension Persona.
 * Copyright (C) 2012 Tyler Romeo <tylerromeo@gmail.com>
 *
 * Extension:Persona is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * Extension:Persona is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with Extension:Persona.  If not, see <http://www.gnu.org/licenses/>.
 */

class ApiPersona extends ApiBase {
	function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	function getDescription() {
		return 'Process Persona login requests and login user if valid.';
	}

	function mustBePosted() {
		return true;
	}

	function isReadMode() {
		return false;
	}

	function getAllowedParams() {
		return array(
			'assertion' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'token' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'stickhttps' => array(
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_REQUIRED => false
			)
		);
	}

	function getParamDescription() {
		return array(
			'assertion' => 'Token received from Mozilla Persona.',
			'token' => 'Login token retrieved from login form.',
			'stickhttps' => 'Whether to set cookies as secure or not.'
		);
	}

	function getResultProperties() {
		return array( '' => array(
			'status' => array(
				ApiBase::PROP_TYPE => array(
					'okay',
					'failure',
					'error',
					'dberror',
					'invaliduser',
					'multipleusers'
				)
			),
			'email' => array(
				ApiBase::PROP_TYPE => 'string',
				ApiBase::PROP_NULLABLE => true
			),
			'audience' => array(
				ApiBase::PROP_TYPE => 'string',
				ApiBase::PROP_NULLABLE => true
			),
			'expires' => array(
				ApiBase::PROP_TYPE => 'string',
				ApiBase::PROP_NULLABLE => true
			),
			'issuer' => array(
				ApiBase::PROP_TYPE => 'string',
				ApiBase::PROP_NULLABLE => true
			),
			'reason' => array(
				ApiBase::PROP_TYPE => 'string',
				ApiBase::PROP_NULLABLE => true
			),
			'message' => array(
				ApiBase::PROP_TYPE => 'string',
				ApiBase::PROP_NULLABLE => true
			)
		) );
	}

	function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'sessionfailure' ),
			array( 'actionthrottledtext' ),
			array( 'code' => 'insecure', 'info' => 'Secure login is enabled, and an insecure (non-HTTPS) request was made.' ),
			array( 'code' => 'failure', 'info' => 'The Persona verification API failed to verify your assertion.' ),
			array( 'code' => 'error', 'info' => 'There was some sort of external server error.' ),
			array( 'code' => 'dberror', 'info' => 'An internal database error occurred.' ),
			array( 'code' => 'invaliduser', 'info' => 'No valid user matched the assertion token provided' ),
			array( 'code' => 'multipleusers', 'info' => 'Multiple users with the same email matched.' )
		) );
	}

	function execute() {
		global $wgSecureLogin;
		if( $wgSecureLogin && WebRequest::detectProtocol() !== 'https' ) {
			$this->dieUsage( 'Secure login is enabled, and an insecure (non-HTTPS) request was made.', 'insecure' );
		}

		$params = $this->extractRequestParams();

		// Check login token and throttling as is done in LoginForm::authenticateUserData.
		// Note that since we do not yet know the username of the login target, the throttle
		// is set for an empty user, effectively making this a per-IP only throttle.
		if( !LoginForm::getLoginToken() ) {
			LoginForm::setLoginToken();
			$this->dieUsageMsg( 'sessionfailure' );
		} elseif( LoginForm::incLoginThrottle( '' ) === true ) {
			$this->dieUsageMsg( 'actionthrottledtext' );
		} elseif( $params['token'] !== LoginForm::getLoginToken() ) {
			$this->dieUsageMsg( 'sessionfailure' );
		}

		// Contact the verification server.
		$assertion = $params['assertion'];
		$response = Http::post(
			'https://verifier.login.persona.org/verify',
			array(
				'caInfo' => __DIR__ . '/persona.crt',
				'postData' => wfArrayToCgi( array(
					'assertion' => $assertion,
					'audience' => wfExpandUrl( '/', $wgSecureLogin ? PROTO_HTTPS : PROTO_HTTP )
				) )
			)
		);
		$result = (array) FormatJson::decode( $response );

		if( !isset( $result['status'] ) || $result['status'] !== 'okay' ) {
			// Bad assertion. Do nothing, as the response itself has
			// sufficient information.
			$result['status'] = 'failure';
		} elseif( $result['audience'] != wfExpandUrl( '/', $wgSecureLogin ? PROTO_HTTPS : PROTO_HTTP ) ) {
			// Weird. Audience was returned differently.
			$result['status'] = 'error';
		} else {
			// Valid token. Login the user.
			$dbr = wfGetDB( DB_MASTER );
			$res = $dbr->select(
				'user',
				User::selectFields(),
				array( 'user_email' => $result['email'] )
			);

			if( $res === false ) {
				$result['status'] = 'dberror';
			} elseif( $res->numRows() == 0 ) {
				$result['status'] = 'invaliduser';
			} elseif( $res->numRows() > 1 ) {
				$result['status'] = 'multipleusers';
			} else {
				// We're good to go. Login the user.
				$user = User::newFromRow( $res->current() );
				$this->getContext()->setUser( $user );
				$user->invalidateCache();

				// Set cookies.
				if( $wgSecureLogin && empty( $params['stickhttps'] ) ) {
					$user->setCookies( $this->getRequest(), false );
				} else {
					$user->setCookies( $this->getRequest() );
				}

				LoginForm::clearLoginToken();
				ApiQueryInfo::resetTokenCache();

				// Add injected HTML as an optional message.
				$injected_html = '';
				wfRunHooks( 'UserLoginComplete', array( &$user, &$injected_html ) );
				$result['message'] = $injected_html;
			}
		}

		$this->getResult()->addValue( null, 'login', $result );
	}
}
