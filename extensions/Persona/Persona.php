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

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'Persona',
	'version' => 0.5,
	'author' => 'Tyler Romeo',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Persona',
	'descriptionmsg' => 'persona-desc'
);

$wgPersonaLoginAnywhere = true;

$wgHooks['BeforePageDisplay'][] = 'efAddPersonaModule';
$wgHooks['UserLoginForm'][] = 'efAddPersonaLogin';
$wgHooks['PersonalUrls'][] = 'efAddPersonaLinks';
$wgAutoloadClasses['ApiPersona'] = __DIR__ . '/ApiPersona.php';
$wgAPIModules['persona'] = 'ApiPersona';
$wgExtensionMessagesFiles['Persona'] = __DIR__ . '/Persona.i18n.php';

$wgResourceModules['ext.persona'] = array(
	'scripts' => array( 'js/persona.js', 'js/persona_hooks.js' ),
	'styles' => array(),
	'messages' => array(),
	'dependencies' => array( 'mediawiki.api', 'mediawiki.Title' ),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Persona'
);

/**
 * Add the Persona module to the OutputPage.
 *
 * @param &$out OutputPage object
 * @param &$skin Skin object
 */
function efAddPersonaModule( OutputPage &$out, Skin &$skin ) {
	global $wgPersonaLoginAnywhere;
	if( !$wgPersonaLoginAnywhere ) {
		return true;
	} elseif( !isset( $_SESSION ) ) {
		wfSetupSession();
	}

	$out->addModules( 'ext.persona' );
	if( !LoginForm::getLoginToken() ) {
		LoginForm::setLoginToken();
	}
	$out->addHTML( Html::input(
		'wpLoginToken',
		LoginForm::getLoginToken(),
		'hidden'
	) );
	return true;
}

/**
 * Add the Persona login button and necessary JavaScript modules
 * to the login form.
 *
 * @param $template QuickTemplate
 */
function efAddPersonaLogin( $template ) {
	$context = RequestContext::getMain();
	$out = $context->getOutput();
	$out->addModules( 'ext.persona' );

	$label = wfMessage( 'persona-login' )->escaped();
	$personaButton = Html::input( 'wpPersona', $label, 'button', array( 'id' => 'wpPersona' ) );
	$template->set( 'header', $personaButton );
	return true;
}

/**
 * Add persona login button to personal URLs.
 *
 * @param $personal_urls Array of personal URLs
 * @param $title Title currently being viewed
 */
function efAddPersonaLinks( array &$personal_urls, Title $title ) {
	global $wgPersonaLoginAnywhere;
	if( $wgPersonaLoginAnywhere && !isset( $personal_urls['logout'] ) ) {
		$context = RequestContext::getMain();
		$out = $context->getOutput();
		$out->addModules( 'ext.persona' );
                # var_dump($personal_urls); exit();
                unset($personal_urls['anonlogin']);
		$personal_urls['personalogin'] = array(
			'text' => wfMessage( 'persona-login' ),
			'href' => '#',
			'active' => $title->isSpecial( 'Userlogin' )
		);
	}
	return true;
}
