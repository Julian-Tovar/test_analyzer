<?php
// Perform the XML file analysis

require("classes/BehatTest.php");

use bta\BehatTest as BehatTest;

function parse_behat_xml($branch = "master", $from = null, $to = null) {
    global $verbose, $tmp_dir, $csv, $csv_file, $urls_file, $show_all;
    $dirs_to_parse = unzip_build_files($branch, $from, $to);
    $builds = array();
    $tests = array();
    $paths = array();
    foreach ($dirs_to_parse as $dir) {
        preg_match("/(\d+)$/", $dir, $match);
        array_push($builds, $match[1]);
    }

    if ($csv) {
        if (is_null($from) && is_null($to)) {
            $from_build = max_build_num($branch);
            $to_build = $from_build;
            $csv_file = $tmp_dir . string_to_legal_string($branch) . "_" . $to_build . ".csv";
            $urls_file = $tmp_dir . "urls_" . string_to_legal_string($branch) . "_" . $to_build . ".csv";
        } elseif (is_null($from) && !is_null($to)) {
            $from_build = min_build_num($branch);
            $csv_file = $tmp_dir . string_to_legal_string($branch) . "_" . $from_build . "_" . $to . ".csv";
            $urls_file =  $tmp_dir . "urls_" . string_to_legal_string($branch) . "_" . $from_build . "_" . $to . ".csv";
        } elseif (!is_null($from) && is_null($to)) {
            $to_build = max_build_num($branch);
            $csv_file = $tmp_dir . string_to_legal_string($branch) . "_" . $from . "_" . $to_build . ".csv";
            $urls_file = $tmp_dir . "urls_" . string_to_legal_string($branch) . "_" . $from . "_" . $to_build . ".csv";
        }

        $csv_fh = fopen($csv_file, "w");
        $csv_headers = "test_composite_name,test_path,feature_name,scenario_name,";
        foreach ($builds as $idx) {
            $csv_headers .= "execution_time_$idx,status_$idx,cause_$idx,";
        }

        if (!file_exists($urls_file)) {
            echo "Notice: Proceeding without build URLs and dates. Rerun with --user if you need them\n";
        } else {
            $urls_fh = fopen($urls_file, "r");
            $csv_headers .= fgets($urls_fh);
            fclose($urls_fh);
        }

        $csv_headers .= "\n";
        echo "Generating the CSV file $csv_file\n";
        fwrite($csv_fh, $csv_headers);
    }

    BehatTest::$total_builds = count($dirs_to_parse);
    foreach ($dirs_to_parse as $dir) {
        $xml_files = glob($dir . "/junit_files/*.xml");
        if ($verbose) {
            echo "Tests to be parsed in branch: $branch, build: " . pathinfo($dir, PATHINFO_FILENAME) . "\n";
            foreach ($xml_files as $file) {
                echo "  " . xml_name_to_test_name($file) . "\n";
            }
        }

        preg_match("/(\d+)$/", $dir, $match);
        $build_num = $match[1];
        $relative_build_num = array_search($build_num, $builds);
        foreach ($xml_files as $file) {
            $cause = '';
            $path = xml_name_to_test_name($file);
            $reader = new XMLReader();
            $reader->open($file);

            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === "testsuite") {
                    $feature = $reader->getAttribute("name");
                    $feature = str_replace('"', "'", $feature);
                    while ($reader->read()) {
                        if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === "testcase") {
                            $scenario = str_replace('"', "'", $reader->getAttribute("name"));
                            $execution_time = $reader->getAttribute("time");
                            $status = $reader->getAttribute("status");
                            if ($csv) {
                                if (!$show_all && $status !== "failed") {
                                    if (in_array($path, $paths)) {
                                        $test = $tests[array_search($path, $paths)];
                                        $build_diff = $relative_build_num - ($test->get_build_count() - 1);
                                        if ($build_diff != 0) {
                                            for ($idx = 0; $idx < $build_diff; $idx++) {
                                                $test->increase_build_count();
                                                $test->append_to_execution_times('');
                                                $test->append_to_statuses('passed');
                                                $test->append_to_causes('');
                                            }
                                        }
                                    }
                                    continue;
                                }
                                while ($reader->read()) {
                                    if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === "failure") {
                                        $whole_fail = $reader->readInnerXml();
                                        preg_match("/<!\[CDATA\[(.*)/", $whole_fail, $summary);
                                        if (count($summary) < 2) {echo "file is $file";}
                                        $cause = str_replace("\n", " ", $summary[1]);
                                        $cause = str_replace('"', "'", $cause);
                                        break;
                                    }
                                }
                                if (!in_array($path, $paths)) {
                                    $test = new BehatTest($feature, $scenario, $path);
                                    $test->increase_build_count();
                                    if ($relative_build_num > 0) {
                                        for ($idx = 0; $idx < $relative_build_num; $idx++) {
                                            $test->append_to_execution_times('');
                                            $test->append_to_statuses('passed');
                                            $test->append_to_causes('');
                                        }
                                    }
                                    $test->append_to_execution_times($execution_time);
                                    $test->append_to_statuses($status);
                                    $test->append_to_causes($cause);
                                    array_push($tests, $test);
                                    array_push($paths, $path);
                                } else {
                                    $test = $tests[array_search($path, $paths)];
                                    $test->increase_build_count();
                                    $test->append_to_execution_times($execution_time);
                                    $test->append_to_statuses($status);
                                    $test->append_to_causes($cause);
                                }
                            } else {
                                if ($status === "failed") {
                                    echo "\nFailure in: " . xml_name_to_test_name($file) . "\n";
                                    echo "It took $execution_time seconds\n";
                                    echo "  Feature: " . $feature . "\n";
                                    echo "  Scenario: " . $scenario . "\n";
                                    while ($reader->read()) {
                                        if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === "failure") {
                                            echo "    Cause: \n";
                                            if ($verbose) {
                                                echo $reader->readInnerXml();
                                            } else {
                                                $whole_fail = $reader->readInnerXml();
                                                preg_match("/<!\[CDATA\[(.*\n.*\n)/", $whole_fail, $summary);
                                                echo $summary[1];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($csv) {
        foreach ($tests as $test) {
            $csv_line = $test->write_test_as_csv();
            fwrite($csv_fh, $csv_line);
        }
        fclose($csv_fh);
    }
}

function unzip_file($input_file, $output_file = null): ?string {

    if (is_null($output_file)) {
        $output_file = substr($input_file, 0, -4);
    }

    if (!is_file($input_file)) {
        echo "File not found: $input_file. Skipping. If you think this is an error, rerun with --user\n\n";
        return null;
    }

    if (!is_dir($output_file)) {
        mkdir($output_file);
    }

    if (is_dir($output_file . "/junit_files")) {
        $files = glob($output_file . "/junit_files/*.xml");
        if (count($files) > 0) {
            echo "Notice: There are XML files in $output_file/junit_files, proceeding with those.\n";
            echo "Delete them if you want to re-unzip the file $input_file.\n\n";
            return $output_file;
        }
    }

    echo "Unzipping file $input_file, this may take a while... ";
    $zip_name = new ZipArchive();
    $ziph = $zip_name->open($input_file);
    if ($ziph === true) {
        $zip_name->extractTo($output_file);
        $zip_name->close();
        echo "Done\n";
    } else {
        echo "Notice: This code should never be executed. Please investigate\n";
        echo __FILE__ . ":" . __LINE__ . "\n";
        debug_print_backtrace();
        exit(1);
    }

    return $output_file;
}

function unzip_build_files($branch = "master", $from = null, $to = null): array {

    $branch_dir = "tmp/" . string_to_legal_string($branch);
    $current_files = glob($branch_dir . "/*.zip");
    $build_nums = array();
    $dirs_to_parse = array();
    foreach ($current_files as $file) {
        array_push($build_nums, intval(pathinfo($file, PATHINFO_FILENAME)));
    }

    if (is_null($from) && is_null($to)) {
        $max_num = max($build_nums);
        $min_num = $max_num;
    } elseif (!is_null($from) xor !is_null($to)) {
        if (!is_null($from)) {
            $max_num = max($build_nums);
            $min_num = $from;
        } else {
            $max_num = $to;
            $min_num = min($build_nums);
        }
    } else {
        $min_num = $from;
        $max_num = $to;
    }
    for ($idx = $min_num; $idx <= $max_num; $idx++) {
        $dir_to_parse = unzip_file($branch_dir . "/" . $idx . ".zip");
        if (is_null($dir_to_parse)) {
            continue;
        }
        array_push($dirs_to_parse, $dir_to_parse);
    }

    return $dirs_to_parse;
}

function xml_name_to_test_name($xml_name): string {

    $path = pathinfo($xml_name, PATHINFO_FILENAME);
    preg_match("/^([\w_]+)(_tests_behat_)([\w_]+)(_feature_\d+)$/", $path, $parts);

    $dir = $parts[1];
    if (substr($dir, 0, 7) === "blocks_") {
        $dir = preg_replace("/_/", "/", $dir, 1);
    } else {
        $dir = preg_replace("/_/", "/", $dir);
    }
    $behat_dir = preg_replace("/_/", "/", $parts[2]);
    $test_file_name = $parts[3];
    $test_suffix = preg_replace("/_feature_(\d+)/", ".feature:$1", $parts[4]);

    return $dir . $behat_dir . $test_file_name . $test_suffix;
}

function max_build_num($branch = "master"): int {
    $branch_dir = "tmp/" . string_to_legal_string($branch);
    $current_files = glob($branch_dir . "/*.zip");
    $build_nums = array();
    foreach ($current_files as $file) {
        array_push($build_nums, intval(pathinfo($file, PATHINFO_FILENAME)));
    }

    return max($build_nums);
}

function min_build_num($branch = "master"): int {
    $branch_dir = "tmp/" . string_to_legal_string($branch);
    $current_files = glob($branch_dir . "/*.zip");
    $build_nums = array();
    foreach ($current_files as $file) {
        array_push($build_nums, intval(pathinfo($file, PATHINFO_FILENAME)));
    }

    return min($build_nums);
}
