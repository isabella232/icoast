<?php

//print "In mapUpdater<br>";
require_once('../includes/globalFunctions.php');
require_once('../includes/userFunctions.php');
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

define("IMAGES_PER_MAP", 10);
$northernLimit = (is_numeric($_GET['north']) ? $_GET['north'] : null);
$southernLimit = (is_numeric($_GET['south']) ? $_GET['south'] : null);
$easternLimit = (is_numeric($_GET['east']) ? $_GET['east'] : null);
$westernLimit = (is_numeric($_GET['west']) ? $_GET['west'] : null);
$projectId = (is_numeric($_GET['projectId']) ? $_GET['projectId'] : null);
$userId = (is_numeric($_GET['userId']) ? $_GET['userId'] : null);
$currentImageId = (is_numeric($_GET['currentImageId']) ? $_GET['currentImageId'] : null);
$imagesToDisplay = Array();


$projectData = retrieve_entity_metadata($DBH, $projectId, 'project');

//print $idsToQuery;
//$query = "SELECT dataset_id FROM datasets WHERE dataset_id IN $idsToQuery" .
//    "ORDER BY region_id, position_in_region";
//$queryResult = run_database_query($query);
//if ($queryResult->num_rows > 0) {
//  $displayOrder = $queryResult->fetch_all(MYSQL_ASSOC);
//}

$imagesInBoundsQuery = <<<EOL
        SELECT image_id, filename, latitude, longitude, feature, city, state, collection_id, position_in_collection
        FROM images WHERE (latitude BETWEEN :southernLimit AND :northernLimit) AND
        (longitude BETWEEN :westernLimit AND :easternLimit) AND
        is_globally_disabled = 0 AND collection_id = {$projectData['post_collection_id']}
        ORDER BY position_in_collection ASC
EOL;
$imagesInBoundsParams = array(
    'southernLimit' => $southernLimit,
    'northernLimit' => $northernLimit,
    'westernLimit' => $westernLimit,
    'easternLimit' => $easternLimit
);
//echo 'Annotated Images<pre>';
//print_r($imagesInBoundsParams);
//echo '</pre>';
$STH = run_prepared_query($DBH, $imagesInBoundsQuery, $imagesInBoundsParams);
$imagesToDisplay = $STH->fetchAll(PDO::FETCH_ASSOC);
//print 'Images In Bounds =' . count($imagesInBounds);
//if (count($imagesInBounds) > 0) {
//    $count = 0;
//    foreach ($imagesInBounds as $imageMatchData) {
//        $imagesToDisplay[] = $imageMatchData;
//        $count++;
//    }
//}





//print 'Count = ' . $count;
////  $imagesToDisplay = $queryResult->fetch_all(MYSQL_ASSOC);
//}
//echo 'Unfiltered images to display<pre>';
//print_r($imagesToDisplay);
//echo '</pre>';
//for ($i = 0; $i < count($imagesToDisplay); $i++) {
//  print $i . ': ' . $imagesToDisplay[$i]['image_id'] . '<br>';
//}


$annotatedImages = array();
$annotatedImagesQuery = "SELECT image_id FROM annotations WHERE user_id = :userId AND project_id = :projectId AND annotation_completed = 1";
$annotatedImagesParams = array(
    'userId' => $userId,
    'projectId' => $projectId
);
$STH = run_prepared_query($DBH, $annotatedImagesQuery, $annotatedImagesParams);
$annotatedImagesResults = $STH->fetchAll(PDO::FETCH_ASSOC);
//$queryResult = run_database_query($query);
//print '$annotatedImagesResults =' . count($annotatedImagesResults);
if (count($annotatedImagesResults) > 0) {
    foreach ($annotatedImagesResults as $imageId) {
        $annotatedImages[] = $imageId['image_id'];
    }
}
//echo 'Annotated Images<pre>';
//print_r($annotatedImages);
//echo '</pre>';

$noImageMatchQuery = "SELECT post_image_id FROM matches WHERE " .
        "post_collection_id = :postCollectionId AND pre_collection_id = :preCollectionId "
        . "AND is_enabled = 0";
$noImageMatchParams = array(
    'postCollectionId' => $projectData['post_collection_id'],
    'preCollectionId' => $projectData['pre_collection_id']
);
$STH = run_prepared_query($DBH, $noImageMatchQuery, $noImageMatchParams);
$noMatchImageResults = $STH->fetchAll(PDO::FETCH_ASSOC);
//$queryResult = run_database_query($query);
//print '$noMatchImageResults =' . count($noMatchImageResults);
$noMatchImageList = array();
if (count($noMatchImageResults) > 0) {
    foreach ($noMatchImageResults as $imageMatchData) {
        $noMatchImageList[] = $imageMatchData['post_image_id'];
    }
}
//echo 'Non Matching Images<pre>';
//print_r($noMatchImageList);
//echo '</pre>';
//$count = 0;
//echo 'Non Matching Images<pre>';
//print_r($imagesToDisplay[0]);
//echo '</pre>';

$userAssignedImageIdPool = null;
$userGroups = find_user_group_membership($DBH, $userId, $projectId, TRUE);
if ($userGroups) {
//    print '<pre>';
//    print_r($userGroups);
//    print '</pre>';
    $imageGroups = find_assigned_image_groups($DBH, $userGroups, TRUE);
    if ($imageGroups) {
//        print '<pre>';
//        print_r($imageGroups);
//        print '</pre>';
        $userAssignedImageIdPool = retrieve_image_id_pool($imageGroups, TRUE, FALSE);
//                print '<pre>';
//        print_r($userAssignedImageIdPool);
//        print '</pre>';
        if (is_array($userAssignedImageIdPool) && count($userAssignedImageIdPool) > 0) {
            for ($i = 0; $i < count($userAssignedImageIdPool); $i++) {
//                print "$i: Checking " . $userAssignedImageIdPool[$i] . '<br>';
                if (has_user_annotated_image($DBH, $userAssignedImageIdPool[$i], $userId) !== 0) {
//                    print 'Removing ' . $userAssignedImageIdPool[$i] . '<br>';
                    array_splice($userAssignedImageIdPool, $i, 1);
                    $i--;
                }
            }
        }
    }
}


if (is_array($userAssignedImageIdPool) && count($userAssignedImageIdPool) > 0) {
    for ($i = 0; $i < count($imagesToDisplay); $i++) {

//  echo $count . ": " . $i . ': ' . $imagesToDisplay[$i]['image_id'] . ' ';
        if (in_array($imagesToDisplay[$i]['image_id'], $annotatedImages) ||
                in_array($imagesToDisplay[$i]['image_id'], $noMatchImageList) ||
                !in_array($imagesToDisplay[$i]['image_id'], $userAssignedImageIdPool) ||
                $imagesToDisplay[$i]['image_id'] == $currentImageId) {
//    echo '- REMOVING ' . $imagesToDisplay[$i]['image_id'] . '<br>';
            array_splice($imagesToDisplay, $i, 1);
            $i--;
        } else {
//    echo '- NO MATCH<br>';
        }
    }
} else {
    for ($i = 0; $i < count($imagesToDisplay); $i++) {

//  echo $count . ": " . $i . ': ' . $imagesToDisplay[$i]['image_id'] . ' ';
        if (in_array($imagesToDisplay[$i]['image_id'], $annotatedImages) ||
                in_array($imagesToDisplay[$i]['image_id'], $noMatchImageList) ||
                $imagesToDisplay[$i]['image_id'] == $currentImageId) {
//    echo '- REMOVING ' . $imagesToDisplay[$i]['image_id'] . '<br>';
            array_splice($imagesToDisplay, $i, 1);
            $i--;
        } else {
//    echo '- NO MATCH<br>';
        }
    }
}
//  $count++;
//print 'images to display = ' . count($imagesToDisplay);
//echo 'Filtered Images to Display<pre>';
//print_r($imagesToDisplay);
//echo '</pre>';
//echo count($imagesToDisplay) . '<br>';
//$query = "SELECT post_image_id FROM matches WHERE " .
//    "post_collection_id = {$projectData['post_collection_id']} AND is_enabled = 0";
//$queryResult = run_database_query($query);
//if ($queryResult->num_rows > 0) {
//  while ($imageMatchData = $queryResult->fetch_assoc()) {
//    $noMatchImageList[] = $imageMatchData['post_image_id'];
//  }
//}
//echo '<pre>';
//print_r($noMatchImageList);
//echo '</pre>';
//for ($i = 0; $i < count($imagesToDisplay); $i++) {
//  echo $i . ' ' . $imagesToDisplay[$i]['image_id'] . ' ';
//  if (in_array($imagesToDisplay[$i]['image_id'], $noMatchImageList)) {
//    echo 'Removing' . $imagesToDisplay[$i]['image_id'] . '<br>';
//    array_splice($imagesToDisplay, $i, 1);
//  }
//}
////echo '<pre>';
////print_r($imagesToDisplay);
////echo '</pre>';
//$orderedImagesToDisplay = Array();
//foreach ($displayOrder as $dataset) {
//  foreach ($imagesToDisplay as $image) {
//    if ($image['dataset_id'] == $dataset['dataset_id']) {
//      $orderedImagesToDisplay[] = $image;
//    }
//  }
//}
////echo 'Ordered images to display<pre>';
////print_r($orderedImagesToDisplay);
////echo '</pre>';
//
//$finalImagesToDisplay = Array();
//$imagesWithinBoundsCount = count($orderedImagesToDisplay);
//$count = 0;
//
//$imagesPerMarker = ceil($imagesWithinBoundsCount / IMAGES_PER_MAP);
//
//for ($i = 0; $i < $imagesWithinBoundsCount; $i += $imagesPerMarker) {
//  $count++;
//  if ($i + $imagesPerMarker < $imagesWithinBoundsCount) {
//    $midImageId = $i + floor($imagesPerMarker / 2);
//    $finalImagesToDisplay["image$count"] = $orderedImagesToDisplay[$midImageId];
//  }
//}

for ($i = 0; $i < count($imagesToDisplay); $i++) {
    $imagesToDisplay[$i]['image_url'] = "images/collections/{$imagesToDisplay[$i]['collection_id']}/main/{$imagesToDisplay[$i]['filename']}";
    $imagesToDisplay[$i]['location_string'] = build_image_location_string($imagesToDisplay[$i]);
//  $imagesToDisplay[$i]['collation_number'] = $imagesPerMarker;
    array_splice($imagesToDisplay[$i], 4, 5);
}

echo json_encode($imagesToDisplay);



