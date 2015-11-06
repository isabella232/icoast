<?php
require("includes/feedback.php");
require("includes/templateCode.php");

?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php print $pageTitle ?></title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <meta name="description" content=" “USGS iCoast - Did the Coast Change?” is a USGS research project to
              construct and deploy a citizen science web application that asks volunteers to compare pre- and
              post-storm aerial photographs and identify coastal changes using predefined tags. This
              crowdsourced data will help USGS improve predictive models of coastal change and educate the
              public about coastal vulnerability to extreme storms.">
        <meta name="author" content="Snell, Poore, Liu, Morgan">
        <meta name="keywords" content="USGS iCoast, iCoast, Department of the Interior, USGS, hurricane, , hurricanes,
              extreme weather, coastal flooding, coast, beach, flood, floods, erosion, inundation, overwash,
              marine science, dune, photographs, aerial photographs, prediction, predictions, coastal change,
              coastal change hazards, beaches, <?php print $projectKeywords ?>">
        <meta name="publisher" content="U.S. Geological Survey">
        <meta name="created" content="20140328">
        <meta name="review" content="20140328">
        <meta name="expires" content="Never">
        <meta name="language" content="EN">
        <link rel="stylesheet" href="css/icoast.css">
        <link rel="stylesheet" href="http://www.usgs.gov/styles/common.css" />
        <link rel="stylesheet" href="css/custom.css">
        <link rel="stylesheet" href="css/tipTip.css">
        <?php
        print $cssLinks;
        ?>
        <style>
<?php
print $feedbackEmbeddedCSS . "\n\r";
print $embeddedCSS;
?>
        </style>
        <script>
            (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
            })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

            ga('create', 'UA-50708501-1', 'auto');
            ga('require', 'displayfeatures');
            ga('send', 'pageview');

        </script>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>

        <script src="scripts/tipTip.js"></script>
        <?php print $javaScriptLinks; ?>
        <script>
<?php
            print $feedbackJavascript . "\n\r";
            print $javaScript . "\n\r";
            print $jQueryDocumentDotReadyCode;
            print $feedbackjQueryDocumentDotReadyCode . "\n\r";
?>
        </script>
    </head>
    <body>
        <!--Header-->
        <?php require('includes/header.txt') ?>

        <!--Page Body-->
        <a href="#skipmenu" title="Skip this menu"></a>
        <div id='navigationBar'>
            <?php print $mainNav ?>
            <div id="OMBInfo"><p>OMB Control Number: 1028-0109<br>Expiration Date: 09/2017</p></div>
        </div>
        <a id="skipmenu"></a>

        <?php
        print $pageBody;
        print $feedbackPageHTML;
        require('includes/footer.txt');
        ?>

        <div id="alertBoxWrapper">
            <div id="alertBoxCenteringWrapper">
                <div id="alertBox">
                    <?php print $alertBoxContent; ?>
                    <div id="alertBoxControls">
                        <?php print $alertBoxDynamicControls; ?>
                        <input type="button" id="closeAlertBox" class="clickableButton" value="Close">
                    </div>
                </div>
            </div>
        </div>

    </body>
</html>


