<?php
// Basic functions that work together, for cURL and to display help messages

$base_url = "https://bamboo.mroomstech.com/artifact/DEV-MOOB";
$psphr1 = null;
$tmp_dir = "tmp/";

function get_latest_build_url($branch = "master"): ?string {
    global $base_url;
    $url = $base_url;

    switch ($branch) {
        case "3.11":
            $url = $url . "30/JOB1/build-latest/Behat-Failed-Dump/junit_files.zip";
            break;
        case "3.11+1":
            $url = $url . "31/JOB1/build-latest/Behat-Failed-Dump/junit_files.zip";
            break;
        case "3.11+2":
            $url = $url . "32/JOB1/build-latest/Behat-Failed-Dump/junit_files.zip";
            break;
        case "master":
            $url = $url . "/JOB1/build-latest/Behat-Failed-Dump/junit_files.zip";
            break;
        default:
            $url = null;
            break;
    }

    return $url;
}

function get_latest_build_num($user, $branch = "master"): string {
    global $tmp_dir;

    switch ($branch) {
        case "3.11":
            $pattern = "/<title>Development - Moodle Behat - release-3.11 (\d+):/";
            $download_url = "https://bamboo.mroomstech.com/browse/DEV-MOOB30/latest";
            break;
        case "3.11+1":
            $pattern = "/<title>Development - Moodle Behat - release-3.11\+1 (\d+):/";
            $download_url = "https://bamboo.mroomstech.com/browse/DEV-MOOB31/latest";
            break;
        case "3.11+2":
            $pattern = "/<title>Development - Moodle Behat - release-3.11\+2 (\d+):/";
            $download_url = "https://bamboo.mroomstech.com/browse/DEV-MOOB32/latest";
            break;
        case "master":
            $pattern = "/<title>Development - Moodle Behat (\d+):/";
            $download_url = "https://bamboo.mroomstech.com/browse/DEV-MOOB/latest";
            break;
        default:
            $pattern = null;
            $download_url = null;
            break;
    }

    if (!is_null($download_url)) {
        download_file($user, $download_url, $tmp_dir . "latest.html");
    }

    $latest = fopen($tmp_dir . "latest.html", "r");
    if (!is_null($pattern)) {
        while (($line = fgets($latest)) !== false) {
            preg_match($pattern, $line, $matches);
            if (isset($matches[1])) {
                $latest_build_num = $matches[1];
                break;
            }
        }
    }
    fclose($latest);

    if (!isset($latest_build_num)) {
        echo "Fatal error: The number of the latest build could not be retrieved.\n";
        echo "Try using the --to option, to set this number manually.\n";
        exit(1);
    }

    return $latest_build_num;
}

function get_value_from_opt($opt, $args): string {
    global $optargs;

    $idx = array_search($opt, $args);

    if (!isset($args[$idx + 1]) || substr($args[$idx + 1], 0, 1) == '-') {
        echo "Please check that each of the optional arguments in use has a value.\n";
        echo "The available optional arguments are:\n";
        print_r($optargs);
        print_help();
        exit(1);
    } else {
        $value = $args[$idx + 1];
    }

    return $value;
}

function create_urls($user, $branch = "master", $from = null, $to = null) : array {

    $urls = array();
    if (is_null($from) && is_null($to)) {
        $max_num = get_latest_build_num($user, $branch);
        $min_num = $max_num;
        $urls += [$max_num => get_latest_build_url($branch)];
    } elseif (!is_null($from) xor !is_null($to)) {
        $max_num = get_latest_build_num($user, $branch);
        $min_num = $max_num - 99;
        if (!is_null($from)) {
            $min_num = $from;
        } else {
            $max_num = $to;
        }
    } else {
        $min_num = $from;
        $max_num = $to;
    }
    for ($idx = $min_num; $idx <= $max_num; $idx++) {
        $urls += [$idx => create_url_with_num($idx, $branch)];
    }

    return $urls;

    // Moodle Behat
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB/JOB1/build-936/Behat-Failed-Dump/junit_files.zip
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB/JOB1/build-927/Behat-Failed-Dump/junit_files.zip
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB/JOB1/build-855/Behat-Failed-Dump/junit_files.zip (No fails here, 404)

    // branch 3.11
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB30/JOB1/build-12/Behat-Failed-Dump/junit_files.zip
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB30/JOB1/build-3/Behat-Failed-Dump/junit_files.zip
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB30/JOB1/build-latest/Behat-Failed-Dump/junit_files.zip

    // branch 3.11+1
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB31/JOB1/build-8/Behat-Failed-Dump/junit_files.zip
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB31/JOB1/build-17/Behat-Failed-Dump/junit_files.zip
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB31/JOB1/build-latest/Behat-Failed-Dump/junit_files.zip (latest finished tests)
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB/JOB1/build-latest/Behat-Failed-Dump/junit_files.zip

    // branch 3.11+2
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB32/JOB1/build-1/Behat-Failed-Dump/junit_files.zip
    // https://bamboo.mroomstech.com/artifact/DEV-MOOB32/JOB1/build-latest/Behat-Failed-Dump/junit_files.zip

    // failure example: junit_files/enrol_meta_tests_behat_enrol_meta_feature_82.xml
}

function create_url_with_num($num, $branch = "master"): string {
    global $base_url;
    $url = $base_url;

    switch ($branch) {
        case "3.11":
            $url = $url . "30/JOB1/build-" . $num . "/Behat-Failed-Dump/junit_files.zip";
            break;
        case "3.11+1":
            $url = $url . "31/JOB1/build-" . $num . "/Behat-Failed-Dump/junit_files.zip";
            break;
        case "3.11+2":
            $url = $url . "32/JOB1/build-" . $num . "/Behat-Failed-Dump/junit_files.zip";
            break;
        case "master":
            $url = $url . "/JOB1/build-" . $num . "/Behat-Failed-Dump/junit_files.zip";
            break;
        default:
            $url = null;
            break;
    }

    return $url;
}

function download_test_results($user, $branch = "master", $from = null, $to = null, $dir_suffix = null) {
    global $psphr1, $tmp_dir;

    if (is_null($psphr1)) {
        authenticate_user($user);
    }

    echo "\nInitiating file download, please be patient\n";
    echo "If you get a timeout error, please check your VPN connection\n\n";

    $urls = create_urls($user, $branch, $from, $to);
    $idxs = array_keys($urls);
    $min_num = $idxs[0];
    $max_num = end($idxs);
    $branch_dir = $tmp_dir . string_to_legal_string($branch);
    $branch_dir = is_null($dir_suffix) ? $branch_dir . "/" : $branch_dir . $dir_suffix . "/";
    if (!is_dir($branch_dir)) {
        mkdir($branch_dir, 0775, true);
    }

    for ($idx = $min_num; $idx <= $max_num; $idx++) {
        $url = $urls[strval($idx)];
        $file = $branch_dir . $idx . ".zip";
        if (file_exists($file)) {
            unlink($file);
        }
        echo "Downloading file: $file... ";
        if (download_file($user, $url, $file)) {
            echo "Done\n";
        }
    }
}

function download_file($user, $url, $output_file): bool {
    global $psphr1;
    if (is_null($psphr1)) {
        authenticate_user($user);
    }

    if (file_exists($output_file)) {
        unlink($output_file);
    }

    $ch = curl_init($url); // cURL handle.

    $fh = fopen($output_file, "w"); // File handle.
    $usp = $user . ":" . base64_decode($psphr1);
    $timeout = 60;

    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // The same as -L.
    curl_setopt($ch, CURLOPT_FAILONERROR, true); // The same as --fail.
    curl_setopt($ch, CURLOPT_USERPWD, $usp); // The same as --user in cURL.
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

    curl_setopt($ch, CURLOPT_FILE, $fh);

    //curl_setopt($ch, CURLOPT_VERBOSE, true);

    curl_exec($ch);
    if(curl_error($ch)) {
        echo "There was a cURL error:\n";
        echo curl_error($ch) . "\n";
        echo "The file $output_file was not downloaded\n\n";
        unlink($output_file);
        return false;
    }
    curl_close($ch);
    fclose($fh);
    return true;
}

function authenticate_user($user) {
    global $psphr1;
    // Wait for password input
    echo "Enter passphrase for Bamboo user '$user'\n";
    $psphr1 = shell_exec("/usr/bin/env sh -c 'stty -echo; read -r psphr; stty echo; echo \$psphr'");
    $psphr1 = base64_encode(trim($psphr1));
}

/**
 * Converts characters in a string, that may be illegal (on some systems) into legal characters
 *
 * @param $string
 * @return string
 */
function string_to_legal_string($string): string {
    $legal_string = preg_replace('/\./', 'd', $string);
    $legal_string = preg_replace('/\+/', 'p', $legal_string);
    return $legal_string;
}

function print_description() {
    global $sn;
    $description = <<<EOT
Description:
    This script facilitates the realization of time series analysis and cross
    sectional analysis of the results of Behat tests. It operates over the
    standard XML output files that come as one of the artifacts from executing
    a Behat test suite, in order to produce console output or CSV output files
    which can then be used to perform said analyses of the results of Behat
    tests.

    If you are unsure about whether or not an XML file can be processed by this
    script, open that file, it should follow this structure:

    <?xml version="1.0" encoding="UTF-8"?>
    <testsuites>
      <testsuite name="Name of the feature" tests="1">
        <testcase name="Name of the scenario" time="3.042" status="passed"/>
      </testsuite>
    </testsuites>

    As can be seen, the <testsuite> element is used to represent features, and
    the <testcase> element is for scenarios.

    The filename of the XML file can be useful for this script, it should
    follow the structure: relative_path_to_feature_file_feature_linenum.xml
    where linenum represents the line number of the test inside the Behat file,
    for example the file dir1_subdir1_tests_behat_file1_feature_41.xml means
    that the XML file contains the result of executing the Behat test in the
    location dir1/subdir1/tests/behat/file1.feature:41

    If you work with a Continuous Integration and Deployment tool such as
    Bamboo, you can use this script to download the XML files for analysis
    directly from Bamboo, by using the --user option (to set the Bamboo user).
    For this, make sure to be logged into Bamboo before using this script (if
    your password doesn't work here, you may need to log in again using
    Bamboo's GUI).

    In order to perform time series analysis, you can use this script to
    download older Bamboo Behat build artifacts, by using the --from and --to
    options. Keep in mind that Bamboo has a build history expiration policy,
    so it commonly stores the last 100 builds only (or another number).

    When using only --from, the value of --to is set to the latest build by
    default. When using only --to, the value of --from is set to the first
    available build by default. When using none of those two, the latest build
    of the selected branch is taken.

    This script retrieves the XML files from the master branch by default.
    If you want to use another branch, use the --branch option, for example
    '--branch 3.11+1' to use the 3.11+1 branch.
    
    You can also use this script without downloading any files (in case you
    already have those files). For this, simply do not use the --user option.
    If your files are named according to branches, from, and to numbers, you
    can use the options --branch, --from, and --to, accordingly.


EOT;

    echo $description;
    echo "Usage:\n  To download and operate over the XMLs from Bamboo between the builds\n  DEV-MOOB-927 and DEV-MOOB-936 (for example)\n";
    echo "\tphp " . $sn . " --user bamboo_username --from 927 --to 936\n\n";
    echo "  To operate over the XML files inside a directory\n";
    echo "\tphp " . $sn . " dir1\n\n";
    echo "  To operate over the latest Bamboo build from the 3.11+1 branch\n";
    echo "\tphp " . $sn . " --user bamboo_username --branch 3.11+1\n\n";
}

function print_help () {
    global $sn;
    echo "Usage:\n  To download and operate over the XMLs from Bamboo between the builds\n  DEV-MOOB-927 and DEV-MOOB-936 (for example)\n";
    echo "\tphp " . $sn . " --user bamboo_username --from 927 --to 936\n\n";
    echo "  To operate over the XML files inside a directory\n";
    echo "\tphp " . $sn . " dir1\n\n";
    echo "  To operate over the latest Bamboo build from the 3.11+1 branch\n";
    echo "\tphp " . $sn . " --user bamboo_username --branch 3.11+1\n\n";
}
