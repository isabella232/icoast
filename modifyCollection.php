<?php
$pageModifiedTime = filemtime(__FILE__);
require('includes/pageCode/modifyCollectionCode.php');
$pageBody = <<<HTML
        <div id="adminPageWrapper">
            $adminNavHTML
            <div id="adminContentWrapper">
                <div id="adminBanner">
                    <p>You are logged in as <span class="userData">$maskedEmail</span>.</p>
                </div>
                <div>
                    <h1> iCoast "{$collectionMetadata['name']}" Collection Creator</h1>
                    $modifyPageHTML


                </div>
            </div>
        </div>
HTML;

require('includes/template.php');
