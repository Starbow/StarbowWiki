# The Starbow Wiki

The wiki is a mostly vanilla mediawiki installation.

We've added the persona extension. To activate it and configure it add the
following to the end of your LocalSettings.php file:

```php
require_once __DIR__ . "/extensions/Persona/Persona.php";

# Prevent new user registrations except by sysops
$wgGroupPermissions['*']['createaccount'] = false;
```

