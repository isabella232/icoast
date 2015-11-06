<?php

// -------------------------------------------------------------------------------------------------
/**
 * Finds the number of tags a user selected in a single annotation
 *
 * Function to count the number of tag selections for a given annotation Id.
 *
 * @param int $annotationId An intenger number of the annotationId from the annotations table.
 * @return int A count of the number of entries in the annotation_selections table that have the annotation
 * ID sepecified. This is the number of tags the user selected in this annotation.
 */
function tagsInAnnotation($DBH, $annotationId) {
    $tagCountQuery = "SELECT COUNT(*) FROM annotation_selections WHERE annotation_id = :annotationId";
    $tagCountParams['annotationId'] = $annotationId;
    $STH = run_prepared_query($DBH, $tagCountQuery, $tagCountParams);
    return $STH->fetchColumn();
}

// -------------------------------------------------------------------------------------------------
/**
 * Determines the time difference between two date time strings
 *
 * Function to determine the time difference in minutes and seconds between two date time strings.
 *
 * @param string $startDateTime A date/time string representing the start time in a valid Date and Time Format
 *                              (http://www.php.net/manual/en/datetime.formats.php)
 * @param string $endDateTime A date/time string representing the later end time in a valid Date and Time Format
 *                              (http://www.php.net/manual/en/datetime.formats.php)
 * @return string The difference between the two times in minutes and seconds "x min(s) x sec(s)"
 */
function timeDifference($startDateTime, $endDateTime, $verbose = TRUE) {
    $startDateTime = new DateTime($startDateTime);
    $endDateTime = new DateTime($endDateTime);
    // Invaliud method in PHP < v5.3
    // $annotationInterval = $startDateTime->diff($endDateTime);
    $annotationIntervalTS = $endDateTime->format('U') - $startDateTime->format('U');
    $annotationIntervalMinutes = floor($annotationIntervalTS / 60);
    $annotationIntervalSeconds = $annotationIntervalTS % 60;
    if ($verbose) {
        return "$annotationIntervalMinutes min(s) $annotationIntervalSeconds sec(s)";
    } else {
        return "{$annotationIntervalMinutes}m {$annotationIntervalSeconds}s";
    }
}

//require_once("includes/globalFunctions.php");
// -------------------------------------------------------------------------------------------------
/**
 * Generates a random image number based on supplied arguments.
 *
 * Function to generate and return a random image id based on Project Id and User Id (if
 * specified). Result can be filtered to ensure the supplied ID has a valid pre image match (ergo,
 * both images have display images and are not globally disabled) and optionally if it has not
 * already been annotated by the user.
 *
 * @param int $projectId iCoast DB row id of the project in question.
 * @param bool $isFiltered If TRUE ensures the returned id is not for an image the user has already
 * annotated and that the image has a valid pre image match.
 * @param type $postCollectionId Optional. Default = 0. iCoast DB row id of the collection to be
 * used as the post image pool.
 * @param type $preCollectionId Optional. Default = 0. iCoast DB row id of the collection to be
 * used as the pre image pool.
 * @param type $userId Optional. Default = 0. iCoast DB row id of the user.
 * @return int|boolean If sucessful returns a random image id <b>OR</b><br>
 * On failure returns FALSE.
 */
function random_post_image_id_generator($DBH, $projectId, $isFiltered, $postCollectionId = 0, $preCollectionId = 0, $userId = 0) {
//   print "<p><b>In random_post_image_id_generator function.</b><br>Arguments:<br>ProjectId = $projectId<br>
//    Filtered = $isFiltered<br>Post Collection = $postCollectionId<br>Pre Collection = $preCollectionId<br>$userId</p>";
    if (!is_null($projectId) && !is_null($isFiltered) && is_Numeric($projectId) &&
            is_Numeric($postCollectionId) && is_Numeric($preCollectionId) && is_Numeric($userId) &&
            is_bool($isFiltered)) {
        $projectData = retrieve_entity_metadata($DBH, $projectId, 'project');
        if ($userId !== 0) {
            $userGroups = find_user_group_membership($DBH, $userId, $projectId, TRUE);
            if ($userGroups) {
                $imageGroups = find_assigned_image_groups($DBH, $userGroups, TRUE);
                if ($imageGroups) {
                    $imageIdPool = retrieve_image_id_pool($imageGroups, TRUE, TRUE);
                } else {
                    $imageIdPool = retrieve_image_id_pool($projectData['post_collection_id'], FALSE, TRUE);
                }
            } else {
                $imageIdPool = retrieve_image_id_pool($projectData['post_collection_id'], FALSE, TRUE);
            }
        } else {
            $imageIdPool = retrieve_image_id_pool($projectData['post_collection_id'], FALSE, TRUE);
        }
        if (is_array($imageIdPool) && count($imageIdPool) > 0 && !$isFiltered) {
            $imagesCount = count($imageIdPool);
            $randomId = $imageIdPool[mt_rand(0, $imagesCount - 1)];
// print "RETURNING: $randomId Unifltered Random Image Id<br>";
            return $randomId;
        } else if (is_array($imageIdPool) && count($imageIdPool) > 0 && $isFiltered) {
            while (!empty($imageIdPool)) {
                $imagesCount = count($imageIdPool);
                $randomIndex = mt_rand(0, $imagesCount - 1);
                if (array_key_exists($randomIndex, $imageIdPool)) {
                    $randomId = $imageIdPool[$randomIndex];
                    array_splice($imageIdPool, $randomIndex, 1);
                    $imageMatchData = retrieve_image_match_data($DBH, $postCollectionId, $preCollectionId, $randomId);
                    if ($imageMatchData AND $imageMatchData['is_enabled'] == 1) {
                        if ($userId != 0) {
                            if (has_user_annotated_image($DBH, $randomId, $userId) === 0) {
                                /* print "RETURNING: $randomId Filtered (by Match Enabled and Not User Annotated)
                                  Random Image Id<br>"; */
                                return $randomId;
                            } else {
// print "FILTERING: Failed on User Annotation";
                            }
                        } else {
// print "RETURNING: $randomId Filtered (by Match Enabled) Random Image Id<br>";
                            return $randomId;
                        }
                    } else {
//print "FILTERING: Failed on is-Enabled";
                    }
                }
            }
            //////////////////////////////////////////////////////////////////////////////////////////////////
            ///////////////
            //////////////
            //////////////
            // USER HAS COMPLETED THEIR IMAGE GROUP OF ANNOTATIONS. NO MORE IMAGES
            return 'allPoolAnnotated';
        } else if (is_array($imageIdPool) && count($imageIdPool) == 0) {
            return 'poolEmpty';
        }
    }
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Generates a pool of post images available to the user.
 *
 * Function to generate and return a pool of images based on Project Id and User Id (if
 * specified). Result can be filtered to ensure the supplied pool only contains images that have a
 * valid pre image match (ergo, both images have display images and are not globally disabled) and
 * optionally if they have not already been annotated by the user.
 *
 * @param int $projectId iCoast DB row id of the project in question.
 * @param bool $isFiltered If TRUE ensures the returned id is not for an image the user has already
 * annotated and that the image has a valid pre image match.
 * @param type $postCollectionId Optional. Default = 0. iCoast DB row id of the collection to be
 * used as the post image pool.
 * @param type $preCollectionId Optional. Default = 0. iCoast DB row id of the collection to be
 * used as the pre image pool.
 * @param type $userId Optional. Default = 0. iCoast DB row id of the user.
 * @return int|boolean If sucessful returns a random image id <b>OR</b><br>
 * On failure returns FALSE.
 */
function post_image_pool_generator($DBH, $projectId, $isFiltered, $postCollectionId = 0, $preCollectionId = 0, $userId = 0) {
    /* print "<p><b>In random_post_image_id_generator function.</b><br>Arguments:<br>$projectId<br>
      $isFiltered<br>$postCollectionId<br>$preCollectionId<br>$userId</p>"; */
    if (!is_null($projectId) && !is_null($isFiltered) && is_Numeric($projectId) &&
            is_Numeric($postCollectionId) && is_Numeric($preCollectionId) && is_Numeric($userId) &&
            is_bool($isFiltered)) {
        $projectData = retrieve_entity_metadata($projectId, 'project');
        if ($userId !== 0) {
            $userGroups = find_user_group_membership($userId, $projectId, TRUE);
            if ($userGroups) {
                $imageGroups = find_assigned_image_groups($userGroups, TRUE);
                if ($imageGroups) {
                    if ($isFiltered) {
                        $imageIdPool = retrieve_image_id_pool($imageGroups, TRUE, TRUE);
                    } else {
                        $imageIdPool = retrieve_image_id_pool($imageGroups, TRUE, FALSE);
                    }
                } else {
                    if ($isFiltered) {
                        $imageIdPool = retrieve_image_id_pool($projectData['post_collection_id'], FALSE, TRUE);
                    } else {
                        $imageIdPool = retrieve_image_id_pool($projectData['post_collection_id'], FALSE, FALSE);
                    }
                }
            } else {
                if ($isFiltered) {
                    $imageIdPool = retrieve_image_id_pool($projectData['post_collection_id'], FALSE, TRUE);
                } else {
                    $imageIdPool = retrieve_image_id_pool($projectData['post_collection_id'], FALSE, FALSE);
                }
            }
        } else {
            if ($isFiltered) {
                $imageIdPool = retrieve_image_id_pool($projectData['post_collection_id'], FALSE, TRUE);
            } else {
                $imageIdPool = retrieve_image_id_pool($projectData['post_collection_id'], FALSE, FALSE);
            }
        }
        if ($imageIdPool AND $userId == 0) {
// print "RETURNING: $imageIdPool An Unfiltered Pool of images<br>";
            return $imageIdPool;
        }
        if ($imageIdPool && $userId != 0) {
            $userAnnotations = all_user_annotated_images($userId, $projectId);
            for ($i = 0; $i < count($imageIdPool); $i++) {
                if (in_array($imageIdPool[$i], $userAnnotations)) {
                    array_splice($imageIdPool, $i, 1);
                }
            }
            return $imageIdPool;
        }
    } else {
// print "RETURNING: $randomId Filtered (by Match Enabled) Random Image Id<br>";
        return FALSE;
    }
}

// -------------------------------------------------------------------------------------------------
/**
 * Retrieves and returns id's of all images annotated by the user either globall or specific to a
 * project.
 *
 * @param type $userId iCoast DB row id of the user.
 * @param int $projectId Optional. Default = 0, iCoast DB row id of the project in question.
 * @return array|boolean On success returns a 1D indexed array where element values contain
 * image_ids.  <b>OR</b><br>On failure returns boolean FALSE.
 */
function all_user_annotated_images($userId, $projectId = 0) {
    if (empty($userId) || !is_numeric($userId) || !is_numeric($projectId)) {
        return false;
    }

    $query = "SELECT image_id FROM annotations WHERE user_id = $userId AND annotation_completed = 1";
    if ($projectId > 0) {
        $query .= " AND project_id = $projectId";
    }
    $annotatedImagesResult = run_database_query($query);
    if ($annotatedImagesResult) {
        $imageIdsReturn = Array();
        while ($imageId = $annotatedImagesResult->fetch_assoc()) {
            $imageIdsReturn[] = $imageId['image_id'];
        }
// print "RETURNING: imageIdsReturn Array<br>";
        return $imageIdsReturn;
    } else {
        return false;
    }
}


// -------------------------------------------------------------------------------------------------
/**
 * Checks if a user has already annotated a specified image either globally or within a specific
 * project.
 *
 * @param int $postImageId iCoast DB row id of the post image to be checked.
 * @param int $userId iCoast DB row id of the user.
 * @param int $projectId Optional. Default = 0, iCoast DB row id of the project in question.
 * @return int|boolean On success returns a 0 (no annotation found) or 1 (annotation found)
 * <b>OR</b><br> On failure returns FALSE.
 */
function has_user_annotated_image($DBH, $postImageId, $userId, $projectId = 0) {
// print "<p><b>In has_user_annotated_image function.</b><br>Arguments:<br>$postImageId</p>";
    if (is_numeric($postImageId) && is_numeric($userId) && is_numeric($projectId)) {
        $annotationCheckQuery = "SELECT COUNT(*) FROM annotations WHERE user_id = :userId AND
        image_id = :postImageId AND annotation_completed = 1";
        $annotationCheckParams = array(
            'userId' => $userId,
            'postImageId' => $postImageId
        );
        if ($projectId > 0) {
            $annotationCheckQuery .= " AND project_id = :projectId";
            $annotationCheckParams['projectId'] = $projectId;
        }
        $STH = run_prepared_query($DBH, $annotationCheckQuery, $annotationCheckParams);
        $annotationCheckResult = $STH->fetchColumn();
//    print $annotationCheckResult;
//    exit;
//    $annotationCheckResult = run_database_query($annotationCheckQuery);
        if ($annotationCheckResult == 0) {
// print "RETURNING: 0 (No existing image annotation found for the user)<br>";
            return 0;
        } else {
// print "RETURNING: 1 (Existing image annotation found for the user)<br>";
            return 1;
        }
    }
// print "RETURNING: FALSE<br>";
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Finds all images groups a user group is permissioned for.
 *
 * Function to find all image groups a/many user group(s) has been permissioned for based on a
 * supplied list of one or more user groups.
 *
 * @param array $userGroups Either 2D where level 1 values = individual group, level 2 values =
 * group data fields, or  1D where values = user group ids
 * @param bool $IdOnly Optional. Default = FALSE. Determines return type. If TRUE only returns the
 * id's of image groups. If FALSE returns full metadata for the image groups.
 * @return array|boolean If $idOnly = TRUE then returns 1D array where values = image group ids.
 * <b>OR</b><br>If $idOnly = FALSE then returns 2D array wher level 1 values = an array for each
 * image group, level 2 keys and values = individual image group fields from image_group_metadata
 * table in iCoast DB <b>OR</b><br> On failure or no image groups found retuns FALSE.
 */
function find_assigned_image_groups($DBH, $userGroups, $idOnly = FALSE) {
    /* print "<p><b>In find_assigned_image_groups function</b>.<br>Arguments:<br><pre>";
      print_r($userGroups);
      print "</pre></p>"; */

    foreach ($userGroups as $userGroup) {
        if (isset($userGroup['user_group_id'])) {
            $userGroupIds[] = $userGroup['user_group_id'];
        } else {
            $userGroupIds[] = $userGroup;
        }
    }
    $whereString = where_in_string_builder($userGroupIds);

    $imageGroupIdQuery = "SELECT image_group_id FROM user_group_assignments WHERE user_group_id IN "
            . "($whereString)";
    $imageGroupIdParams = array();
    $STH = run_prepared_query($DBH, $imageGroupIdQuery, $imageGroupIdParams);
    $imageGroups = $STH->fetchAll(PDO::FETCH_ASSOC);
//  $imageGroups = run_database_query($imageGroupIdQuery);
    if (count($imageGroups) > 0) {
        foreach ($imageGroups as $singleImageGroup) {
            $imageGroupIds[] = $singleImageGroup['image_group_id'];
        }
        if ($idOnly) {
//            print '<pre>';
//            print_r($imageGroupIds);
//            print '</pre>';
            return $imageGroupIds;
        } else {
// Potential Function
            $whereString = where_in_string_builder($imageGroupIds);
            $imageGroupMetadataQuery = "SELECT * FROM image_group_metadata WHERE image_group_id IN "
                    . "($whereString)";
//      $imageGroupMetadataParams['whereString'] = $whereString;
            $imageGroupMetadataParams = array();
//      $imageGroupMetadata = run_database_query($imageGroupIdQuery);
            $STH = run_prepared_query($DBH, $imageGroupMetadataQuery, $imageGroupMetadataParams);
            $imageGroupMetadata = $STH->fetchAll(PDO::FETCH_ASSOC);
            foreach ($imageGroupMetadata as $singleImageGroupMetadata) {
                $imageGroupResults[] = $singleImageGroupMetadata;
            }
//            print "RETURNING: <pre>";
//            print_r($imageGroupResults);
//            print '</pre>';
            return $imageGroupResults;
        }
    }
//print "RETURNING: FALSE<br>";
    return FALSE;
}

// -------------------------------------------------------------------------------------------------
/**
 * Finds all user groups a user has been assigned to.
 *
 * Function to find all user groups a user has been placed in based on a supplied user Id and
 * optionally filtered by a given project.
 *
 * @param int $userId iCoast DB row id of the user.
 * @param int $projectId Optional. Default = 0. iCoast DB row id of the project to be queried.
 * @param type $idOnly Optional. Default = FALSE. Determines return type. If TRUE only returns the
 * id's of user groups. If FALSE returns full metadata for the user groups.
 * @return array|boolean If $idOnly = TRUE then returns 1D array where values = user group ids.
 * <b>OR</b><br>If $idOnly = FALSE then returns 2D array wher level 1 values = an array for each
 * user group, level 2 keys and values = individual user group fields from user_group_metadata
 * table in iCoast DB <b>OR</b><br> On failure or no image groups found retuns FALSE.
 */
function find_user_group_membership($DBH, $userId, $projectId = 0, $idOnly = FALSE) {
//print "<p><b>In find_user_group_membership function.</b><br>Arguments:<br>$userId<br>$projectId</p>";
// Define variables and PHP settings
    $idArray = array();

    if (!is_null($userId) && is_numeric($userId) && is_numeric($projectId) && is_Bool($idOnly)) {
        $userGroupIdsQuery = "SELECT user_group_id FROM user_groups WHERE user_id = :userId";
        $userGroupIdsParams['userId'] = $userId;
        $STH = run_prepared_query($DBH, $userGroupIdsQuery, $userGroupIdsParams);
        $userGroupIds = $STH->fetchAll(PDO::FETCH_ASSOC);
//        print "User Group Id's";
//        print '<pre>';
//        print_r($userGroupIds);
//        print '</pre>';
        if (count($userGroupIds) > 0) {
            foreach ($userGroupIds as $id) {
                $idArray[] = $id['user_group_id'];
            }
//            print '<pre>';
//            print_r($idArray);
//            print '</pre>';
// Potential Function
            $whereString = where_in_string_builder($idArray);
//            print $whereString;
            $userGroupDetailsQuery = "SELECT * FROM user_group_metadata WHERE user_group_id IN "
                    . "($whereString)";
            $userGroupDetailsParams = array();
            if ($projectId > 0) {
                $userGroupDetailsQuery .= " AND project_id = :projectId";
                $userGroupDetailsParams['projectId'] = $projectId;
            }
            $STH = run_prepared_query($DBH, $userGroupDetailsQuery, $userGroupDetailsParams);
            $userGroupDetails = $STH->fetchAll(PDO::FETCH_ASSOC);

//            print '<pre>';
//            print_r($userGroupDetails);
//            print '</pre>';
            if (count($userGroupDetails) > 0) {
//                print '<pre>';
//                print_r($userGroupDetails);
//                print '</pre>';
                foreach ($userGroupDetails as $details) {
                    if ($idOnly) {
                        $userGroupResults[] = $details['user_group_id'];
                    } else {
                        $userGroupResults[] = $details;
                    }
                }
                /* print "RETURNING: <pre>";
                  print_r($userGroupResults);
                  print '</pre>'; */
//                print '<pre>';
//                print_r($userGroupDetails);
//                print '</pre>';
                return $userGroupResults;
            }
        }
    }
// print "RETURNING: FALSE<br>";
    return FALSE;
}


// -------------------------------------------------------------------------------------------------
/**
 * Finds the details of one image on each side of a supplied image id.
 *
 * Using a supplied image id this function queries the database to find the next and previous image
 * in the photo sequence (as defined in the dataset) and returns an array of metadata for all three
 * images.
 *
 * @param int $photoId iCoast DB row id of the source image.
 * @param int $projectId Optional. Default = NULL. If specified the returned adjacent images will
 * be checked to ensure they contain a valid match in the matches table of the iCoast DB (should
 * only be specified when a post image id is passed as the $imageId argument.
 * @param int $userId Optional. Default = NULL. If supplied images returned must be part of an image
 * group to which the user is a member.
 * @param int $range Optional, Default = 1. Determines the number of images each side of the supplied image
 * that will be returned. Value of 1 willr eturn a total of 3 images. 1 each side and the supplied image
 * in the middle.
 * @param int $searchLimit Optional. Default = 20. The maximum distance from the supplied image (in each
 * direction) that may be searched in trying to find the number of images requested in $range.
 * @return array|boolean On success returns a 2D array where 1st level values contain an array of
 * metadata for each image and the second level keys/values contain databse column names and row
 * data from the images table of the iCoast DB except where $projectId was passed and no adjacent
 * image was found in which case the 2nd level will contain one key "image_id" with value "0"
 * <b>OR</b><br> On failure returns FALSE.
 */
function find_adjacent_images($DBH, $photoId, $projectId = NULL, $userId = NULL, $range = 1, $searchLimit = 20) {

    $photoMetadata = null;
    $projectMetadata = null;
    $userMetadata = null;

    // Check imput validity.
    if (isset($photoId)) {
        settype($photoId, 'integer');
        if (!empty($photoId)) {
            $photoMetadata = retrieve_entity_metadata($DBH, $photoId, 'image');
        }
    }
    if (isset($projectId)) {
        settype($projectId, 'integer');
        if (!empty($projectId)) {
            $projectMetadata = retrieve_entity_metadata($DBH, $projectId, 'project');
        }
    }

    if (isset($userId)) {
        settype($userId, 'integer');
        if (!empty($userId)) {
            $userMetadata = retrieve_entity_metadata($DBH, $userId, 'user');
        }
    }
    settype($range, 'integer');
    settype($searchLimit, 'integer');

    if (!$photoMetadata ||
            (!is_null($projectId) && !$projectMetadata) ||
            (!is_null($userMetadata['user_id']) && !$userMetadata) ||
            empty($range) ||
            empty($searchLimit)) {
        return FALSE;
    }


    $adjacentImageArray = Array();
    $imagesInCollectionQuery = <<<EOL
            SELECT COUNT(*)
            FROM images
            WHERE collection_id = :collectionId
EOL;
    $imagesInCollectionParams['collectionId'] = $photoMetadata['collection_id'];
    $imagesInCollectionResult = run_prepared_query($DBH, $imagesInCollectionQuery, $imagesInCollectionParams);
    $imagesInCollection = $imagesInCollectionResult->fetchColumn();


    $hasUserAssignedImages = FALSE;
    if ($userMetadata) {
        $userGroups = find_user_group_membership($DBH, $userMetadata['user_id'], $projectMetadata['project_id'], TRUE);
        if ($userGroups) {
            $imageGroups = find_assigned_image_groups($DBH, $userGroups, TRUE);
            if ($imageGroups) {
                $userAssignedImageIdPool = retrieve_image_id_pool($imageGroups, TRUE, FALSE);
                if (is_array($userAssignedImageIdPool) && count($userAssignedImageIdPool) > 0) {
                    for ($i = 0; $i < count($userAssignedImageIdPool); $i++) {
                        if (has_user_annotated_image($DBH, $userAssignedImageIdPool[$i], $userMetadata['user_id']) === 1) {
                            array_splice($userAssignedImageIdPool, $i, 1);
                            $i--;
                        }
                    }
                    if (count($userAssignedImageIdPool) > 0) {
                        $hasUserAssignedImages = TRUE;
                    }
                }
            }
        }
    }


    // Ensure the range in the query doesn't exceed the available rows in the dataset.
    $minPosition = $photoMetadata['position_in_collection'] - $searchLimit;
    if ($minPosition <= 0) {
        $minPosition = 1;
    }
    $maxPosition = $photoMetadata['position_in_collection'] + $searchLimit;
    if ($maxPosition > $imagesInCollection) {
        $maxPosition = $imagesInCollection;
    }

    $adjacentImageMetadata = array();
    // Query the iCoast DB for all images with position id's in the defined range.
    $adjacentImageQuery = <<<EOL
            SELECT *
                FROM images
                WHERE collection_id = {$photoMetadata['collection_id']} AND
                position_in_collection BETWEEN $minPosition AND $maxPosition
EOL;
    foreach ($DBH->query($adjacentImageQuery, PDO::FETCH_ASSOC) as $adjacentImage) {
        $adjacentImageMetadata[$adjacentImage['position_in_collection']] = $adjacentImage;
    }
    if (count($adjacentImageMetadata) == 0) {
        return FALSE;
    }
    // Look through the array of adjacent images in searchLimit in ascending order searching for
    // the specified number of images ($range) using ascending position_in_dataset numbers from the current image.
    if ($photoMetadata['position_in_collection'] == $maxPosition) {
        for ($i = 0; $i < $range; $i++) {
            $adjacentImageArray[$i] = array('image_id' => 0);
        }
    } else {
        $tempPositionCounter = $photoMetadata['position_in_collection'] + 1;
        $imagesFound = 0;
        while ($imagesFound < $range) {
            if (isset($adjacentImageMetadata[$tempPositionCounter])) {
                // Initiate a match check if required.
                if ($projectMetadata) {
                    $match = retrieve_image_match_data($DBH, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $adjacentImageMetadata[$tempPositionCounter]['image_id']);
                    if (!$match || $match['is_enabled'] == 0) {
                        $tempPositionCounter++;
                        continue;
                    }
                }
                // Initiate an image validity check.
                if ($hasUserAssignedImages) {
                    if ($adjacentImageMetadata[$tempPositionCounter]['is_globally_disabled'] == 0 &&
                            in_array($adjacentImageMetadata[$tempPositionCounter]['image_id'], $userAssignedImageIdPool)) {
                        // Passed check. Add image details to $adjacentImageArray, break the while loop.
                        $imagesFound++;
                        $adjacentImageArray[$range - $imagesFound] = $adjacentImageMetadata[$tempPositionCounter];
                    }
                } else {
                    if ($adjacentImageMetadata[$tempPositionCounter]['is_globally_disabled'] == 0) {
                        // Passed check. Add image details to $adjacentImageArray, break the while loop.
                        $imagesFound++;
                        $adjacentImageArray[$range - $imagesFound] = $adjacentImageMetadata[$tempPositionCounter];
                    }
                }
            }

            // Increment the tempPositionCounter to look for the next image in the set.
            $tempPositionCounter++;
            // If we have search all ascending images in the query results and nothing was found
            // set the array manually with an identified to show no match found.
            if ($tempPositionCounter > $maxPosition) {
                for ($imagesFound++; $imagesFound <= $range; $imagesFound++) {
                    $adjacentImageArray[$range - $imagesFound] = array('image_id' => 0);
                }
                break;
            }
        }
    }

    // Add the current image to the middle of the $adjacentImageArray.
    $adjacentImageArray[$range] = $photoMetadata;

    // Look through the array of adjacent images in searchLimit in decending order searching for
    // the specified number of images ($range) using decending position_in_dataset numbers from the current image.




    if ($photoMetadata['position_in_collection'] == $minPosition) {
        for ($i = $range + 1; $i <= $range * 2; $i++) {
            $adjacentImageArray[$i] = array('image_id' => 0);
        }
    } else {
        $tempPositionCounter = $photoMetadata['position_in_collection'] - 1;
        $imagesFound = 0;
        while ($imagesFound < $range) {
            if (isset($adjacentImageMetadata[$tempPositionCounter])) {
                // Initiate a match check if required.
                if ($projectMetadata) {
                    $match = retrieve_image_match_data($DBH, $projectMetadata['post_collection_id'], $projectMetadata['pre_collection_id'], $adjacentImageMetadata[$tempPositionCounter]['image_id']);
                    if (!$match || $match['is_enabled'] == 0) {
                        $tempPositionCounter--;
                        continue;
                    }
                }
                // Initiate an image validity check.
                if ($hasUserAssignedImages) {
                    if ($adjacentImageMetadata[$tempPositionCounter]['is_globally_disabled'] == 0 &&
                            in_array($adjacentImageMetadata[$tempPositionCounter]['image_id'], $userAssignedImageIdPool)) {
                        // Passed check. Add image details to $adjacentImageArray, break the while loop.
                        $imagesFound++;
                        $adjacentImageArray[$range + $imagesFound] = $adjacentImageMetadata[$tempPositionCounter];
                    }
                } else {
                    if ($adjacentImageMetadata[$tempPositionCounter]['is_globally_disabled'] == 0) {
                        // Passed check. Add image details to $adjacentImageArray, break the while loop.
                        $imagesFound++;
                        $adjacentImageArray[$range + $imagesFound] = $adjacentImageMetadata[$tempPositionCounter];
                    }
                }

                // Decrement the tempPositionCounter to look for the previous image in the set.
                $tempPositionCounter--;
                if ($tempPositionCounter < $minPosition) {
                    // If we have search all ascending images in the query results and nothing was found
                    // set the array manually with an identified to show no match found.
                    for ($imagesFound++; $imagesFound <= $range; $imagesFound++) {
                        $adjacentImageArray[$range + $imagesFound] = array('image_id' => 0);
                    }
                    break;
                }
            }
        }
    }
    return $adjacentImageArray;
}