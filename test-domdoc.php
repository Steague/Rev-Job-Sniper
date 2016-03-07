<?php

$html = '<div><span><div class="oe-state-header oe-claim">
<form action="/workspace/ClaimTranslation?projectNumber=TR0636242472P-1" id="claim-form" method="post"><input name="__RequestVerificationToken" type="hidden" value="NfpaIuvoc5Jub-ZXWOiGgp4yj7ujrecQ57BKzpyx1FkNS-RDWv4P3xt0gfZLutbagge1oPFRSKasQ4hxjGlR-xUJ4f_NML7y8yAqUI7fraEFvtpz0">    <div class="btn-group pull-left">
<a class="btn  windows" href="#" id="flag-problem-btn"><span class="btn-label">Report Problem</span></a>    </div>
    <span class="oe-state-msg">
If claimed, due in 2 hours, 30 minutes    </span>
    <div class="btn-group pull-right">
<a class="btn ws-submit btn-wide windows" href="#" id="claim-btn"><span class="btn-label">Claim</span></a>    </div>
</form>    </div></div></span>';

// $dom = new DomDocument();
// $dom->loadHTML($html);

// $form = $dom->getElementById("claim-form");
// $node = $form->firstChild;
// $requestVerificationToken = null;
// while (gettype($node) == "object") {
// 	if ($node->getAttribute('name') != "__RequestVerificationToken") {
// 		$node = $node->nextSibling;
// 		continue;
// 	}

// 	$requestVerificationToken = $node->getAttribute('value');
// 	break;
// }
//"__RequestVerificationToken"
$requestVerificationToken = null;
$pattern = '/<input name="__RequestVerificationToken" type="hidden" value="([a-zA-Z0-9-_]+)"/';
preg_match($pattern, $html, $matches);
print_r($matches[1]);