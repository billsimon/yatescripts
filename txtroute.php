#!/usr/bin/php -q
<?php
/**
 * txtroute.php
 * Re-routes incoming INVITES by using a TXT record lookup
 *
 */

/* A script for the Yate PHP interface
   Add in extmodule.conf

   [scripts]
   txtroute.php=
*/
require_once("libyate.php");

/* Always the first action to do */
Yate::Init();

/* Comment the next line to get output only in logs, not in rmanager */
Yate::Output(true);

/* Restart if terminated */
Yate::SetLocal("restart",true);

/* Install a handler for the call routing message */
Yate::Install("call.route",5);

/* The main loop. We pick events and handle them */
for (;;) {
	$ev=Yate::GetEvent();
	/* If Yate disconnected us then exit cleanly */
	if ($ev === false)
		break;
	/* Empty events are normal in non-blocking operation.
	   This is an opportunity to do idle tasks and check timers */
	if ($ev === true) {
		continue;
	}
	/* If we reached here we should have a valid object */
	switch ($ev->type) {
	case "incoming":
		$userPart = '';  // Reset variables each time around
		$domainPart = '';
		$ev->handled = false;

		preg_match('/sip:(.*)@([^;]+)/', $ev->params['sip_uri'], $parts);  // parse a SIP URI
		$userPart = preg_replace('/[^[:alnum:]]/', '', $parts[1]);  // remove non-alpha/num
		$domainPart = $parts[2];
		Yate::Output("Looking up TXT for $userPart@$domainPart");

		$lookup = dns_get_record("sip-$userPart.$domainPart", DNS_TXT); // sip-user.dom.ain IN TXT?
		if (isset($lookup[0]['txt'])) {  // only acts on the first TXT record found for the name
			$dest = $lookup[0]['txt'];
			Yate::Output("Rerouting to sip:" . $dest);
			$ev->retval = 'sip/sip:' . $dest;
			$ev->setParam('redirect', 'true');
			unset($lookup);  // reset for next time around this loop
		} else {
			Yate::Output("Not found");
			$ev->retval = '-';
			$ev->setparam('error', '404');
		}
		/* This is extremely important.
		   We MUST let messages return, handled or not */
		$ev->handled = TRUE;  // this is the only routing in all cases. Either redirect or 404.
		$ev->Acknowledge();
		break;
	case "answer":
		Yate::Output("PHP Answered: " . $ev->name . " id: " . $ev->id);
		break;
	case "installed":
		Yate::Output("PHP Installed: " . $ev->name);
		break;
	case "uninstalled":
		Yate::Output("PHP Uninstalled: " . $ev->name);
		break;
	default:
		Yate::Output("PHP Event: " . $ev->type);
	}
}

Yate::Output("PHP: bye!");

/* vi: set ts=8 sw=4 sts=4 noet: */
?>
