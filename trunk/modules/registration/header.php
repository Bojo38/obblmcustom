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
define('USERNAME_ERROR', 'Le nom d\'utilsiateur existe d�j� ou fait moins de3 caract�res.');
define('PASSWORD_ERROR', 'Le mot de passe doit faire au moins 5 caract�res.');
define('EMAIL_ERROR', 'L`\'adresse e-mail n\'est pas valide.');
define('EMAIL_SUBJECT', 'Nouvel utilisateur ORACL');
define('EMAIL_MESSAGE', 'Vous avez re�u une nouvelle demande d\'enregistrement. Pour activer vous devez annuler la retraite du coach.

coach: ');
define('SEND_EMAIL_ERROR', 'L\'enregistrement a �t� un succ�s, mais l\'administrateur n\'a pas re�u la notification par mail. Cela risque de prendre du temps pour activer le compte.');
define('SUCCESS_MSG', 'L\'enregistrement a �t� un succ�s. Un administrateur doit activer votre compte ou vous contacter pour v�rification.');
define('USERNAME_RESET_ERROR', 'Le nom d\'utilisateur n\'existe pas.');
?>