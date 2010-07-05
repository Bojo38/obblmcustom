<?php
global $db_prefix;

// User table information
define('USERTABLE', $db_prefix+'coaches');
define('USERNAME', 'name');
define('PASSWORD', 'passwd');
define('EMAIL', 'mail');
define('ACTIVATION', 'retired');
define('NOT_ACTIVATED', 1);
define('IS_ACTIVATED', 0);
define('ACCESS', 'ring');
define('ACCESS_LEVEL', Coach::T_RING_GLOBAL_NONE);

// Error messages *NOTE: Error messages are not concatenated as only one needs to be seen by the user.
define('USERNAME_ERROR', 'Le nom d\'utilsiateur existe déjà ou fait moins de3 caractères.');
define('PASSWORD_ERROR', 'Le mot de passe doit faire au moins 5 caractères.');
define('EMAIL_ERROR', 'L`\'adresse e-mail n\'est pas valide.');
define('EMAIL_SUBJECT', 'Nouvel utilisateur ORACL');
define('EMAIL_MESSAGE', 'Vous avez reçu une nouvelle demande d\'enregistrement. Pour activer vous devez annuler la retraite du coach.

coach: ');
define('SEND_EMAIL_ERROR', 'L\'enregistrement a été un succès, mais l\'administrateur n\'a pas reçu la notification par mail. Cela risque de prendre du temps pour activer le compte.');
define('SUCCESS_MSG', 'L\'enregistrement a été un succès. Un administrateur doit activer votre compte ou vous contacter pour vérification.');
define('USERNAME_RESET_ERROR', 'Le nom d\'utilisateur n\'existe pas.');
?>