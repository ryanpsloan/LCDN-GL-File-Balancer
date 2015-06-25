<?php
/**********************************************************************************************************************
  Author: Ryan Sloan
  This process will read a sorted .csv F657 GL file from LCDN and analyze the credits and debits and whether or not
  they balance and output the total and whether or not the balance to a web page
  ryan@paydayinc.com
 *********************************************************************************************************************/
/**
Data Example:

Index: 0            1        2          3        4   5-num   6   7    8-DB   9-CR      10-name
PR061015 WK# 24, 99982473, ER WCA,   6/11/2015, 6508, 200,  20,  60,  2.3,       0, AGNES L OLSEN
PR061015 WK# 24, 99982473, NM-SUI,   6/11/2015, 6510, 200,  20,  60, 5.47,       0, AGNES L OLSEN
PR061015 WK# 24, 99982499, NETPAY,   6/11/2015, 1030, 100,   0,   0,    0,  483.07, AMANDA  JARAMILLO
PR061015 WK# 24, 99982499, OASDI,    6/11/2015, 2210, 100,   0,   0,    0,   44.27, AMANDA  JARAMILLO
PR061015 WK# 24, 99982499, ER OASDI, 6/11/2015, 2210, 100,   0,   0,    0,   44.27, AMANDA  JARAMILLO
PR061015 WK# 24, 99982499, MEDICARE, 6/11/2015, 2220, 100,   0,   0,    0,   10.35, AMANDA  JARAMILLO

 * Note: file must be sorted and filtered by both name(10) and num(5) before upload
 **/

session_start();
if(isset($_FILES)) { //Check to see if a file is uploaded
    try {
        if (($log = fopen("log.txt", "w")) === false) { //open a log file
            //if unable to open throw exception
            throw new RuntimeException("Log File Did Not Open.");
        }

        $today = new DateTime('now'); //create a date for now
        fwrite($log, $today->format("Y-m-d H:i:s") . PHP_EOL); //post the date to the log
        fwrite($log, "--------------------------------------------------------------------------------" . PHP_EOL); //post to log

        $name = $_FILES['file']['name']; //get file name
        fwrite($log, "FileName: $name" . PHP_EOL); //write to log
        $type = $_FILES["file"]["type"];//get file type
        fwrite($log, "FileType: $type" . PHP_EOL); //write to log
        $tmp_name = $_FILES['file']['tmp_name']; //get file temp name
        fwrite($log, "File TempName: $tmp_name" . PHP_EOL); //write to log
        $tempArr = explode(".", $_FILES['file']['name']); //set file name into an array
        $extension = end($tempArr); //get file extension
        fwrite($log, "Extension: $extension" . PHP_EOL); //write to log

        //If any errors throw an exception
        if (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error'])) {
            fwrite($log, "Invalid Parameters - No File Uploaded." . PHP_EOL);
            throw new RuntimeException("Invalid Parameters - No File Uploaded.");
        }

        //switch statement to determine action in relationship to reported error
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                fwrite($log, "No File Sent." . PHP_EOL);
                throw new RuntimeException("No File Sent.");
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                fwrite($log, "Exceeded Filesize Limit." . PHP_EOL);
                throw new RuntimeException("Exceeded Filesize Limit.");
            default:
                fwrite($log, "Unknown Errors." . PHP_EOL);
                throw new RuntimeException("Unknown Errors.");

        }

        //check file size
        if ($_FILES['file']['size'] > 2000000) {
            fwrite($log, "Exceeded Filesize Limit." . PHP_EOL);
            throw new RuntimeException('Exceeded Filesize Limit.');
        }

        //define accepted extensions and types
        $goodExts = array("csv", "xls", "xlsx");
        $goodTypes = array("text/csv", "application/vnd.ms-excel",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");

        //test to ensure that uploaded file extension and type are acceptable - if not throw exception
        if (in_array($extension, $goodExts) === false || in_array($type, $goodTypes) === false) {
            fwrite($log, "This page only accepts .csv/.xls/.xlsx files, please upload the correct format." . PHP_EOL);
            throw new Exception("This page only accepts .csv/.xls/.xlsx files, please upload the correct format.");
        }

        //move the file from temp location to the server - if fail throw exception
        $directory = "/var/www/html/lcdn/lcdnFiles";
        if (move_uploaded_file($tmp_name, "$directory/$name")) {
            fwrite($log, "File Successfully Uploaded." . PHP_EOL);
            //echo "<p>File Successfully Uploaded.</p>";
        } else {
            fwrite($log, "Unable to Move File to /lcdnFiles." . PHP_EOL);
            throw new RuntimeException("Unable to Move File to /lcdnFiles.");
        }


        //rename the file using todays date and time
        $month = $today->format("m");
        $day = $today->format('d');
        $year = $today->format('y');
        $time = $today->format('H:i:s');

        $newName = "$directory/lcdnData-$month-$day-$year-$time.$extension";
        if ((rename("$directory/$name", $newName))) {
            fwrite($log, "File Renamed to: $newName" . PHP_EOL);
            //echo "<p>File Renamed to: $newName </p>";
        } else {
            fwrite($log, "Unable to Rename File: $name" . PHP_EOL);
            throw new RuntimeException("Unable to Rename File: $name");
        }

        //open the stream for file reading
        $handle = fopen($newName, "r");
        if ($handle === false) {
            fwrite($log, "Unable to Open Stream." . PHP_EOL);
            throw new RuntimeException("Unable to Open Stream.");
        } else {
            fwrite($log, "Stream Opened Successfully." . PHP_EOL);
            //echo "<p>Stream Opened Successfully.</p>";
        }

        //echo "<hr>";

        $fileData = array();

        //read the data in line by line
        while (!feof($handle)) {
            $line_of_data = fgets($handle); //gets data from file one line at a time
            $line_of_data = trim($line_of_data); //trims the data
            $fileData[] = explode(",", $line_of_data); //breaks the line up into pieces that the array can store
        }

        //close file reading stream
        fclose($handle);

        //if there is an incomplete last line, remove it
        if (count(end($fileData)) < 11) {
            array_pop($fileData);
        }

        $_SESSION['fileData'] = $fileData;

        //assign the first name in the file
        $name = $fileData[0][10];
        $groups = array();
        $date = $fileData[0][3]; //assign the files date

        //filter data down to relevent information - name, debit, credit and payment code
        foreach($fileData as $row){

            if($row[10] !== $name) { //as iterate through the array check the name and update $name when it changes
                $name = $row[10];

            }
            //set up a new array structure and cast values
            $line = array('number' => (int) $row[5], 'debit' => (float) $row[8], 'credit' => (float) $row[9]);
            $groups[$name][] = $line; //insert into new array

        }

        //Declare variables used for counting and grouping array contents
        $oneCount = 0;
        $twoCount = 0;
        $threeCount = 0;
        $oneHundred = false;
        $twoHundred = false;
        $threeHundred = false;
        $sortedData = array();
        //Iterate through array and separate the data into 100, 200, 300 sections
        foreach($groups as $key => $group) {
            //view group data
            //-------------------------------------------------------
            /*echo "<hr><br>Group: $key<br>";
            echo "Group Count: " .count($group) ."<br>";
            $h = 0;
            foreach($group as $row){
                    echo "$h. ";
                    var_dump($row);
                    echo "<br>";
                    $h++;
            }
            echo "<br><hr><hr><br>";
            echo "$key => <br>";*/
            //----------------------------------------------------------

            $count = count($group);
            $number = $group[0]['number'];
            //echo "Starting Number: $number<br><br>";

            //payment code is either 100, 200, or 300, set boolean variable by checking the value of $number
            if ($number === 100) {
                $oneHundred = true;
                $twoHundred = false;
                $threeHundred = false;
            } else if ($number === 200) {
                $twoHundred = true;
                $oneHundred = false;
                $threeHundred = false;
            } else if ($number === 300) {
                $threeHundred = true;
                $twoHundred = false;
                $oneHundred = false;
            }
            //read through and count the number of lines in various sections of paycodes by employee
            for ($i = 0; $i < $count; $i++) {
                if ($group[$i]['number'] !== $number) {//if payment code !== $number update $number with new code
                    $number = $group[$i]['number'];
                    if ($number === 100) { //set 100 section allowing 100s to be counted
                        $oneHundred = true;
                        $twoHundred = false;
                        $threeHundred = false;
                    } else if ($number === 200) { //set 200 section on allowing 200s to be counted
                        $twoHundred = true;
                        $oneHundred = false;
                        $threeHundred = false;
                    } else if ($number === 300) { //sets 300 section on allowing 300s to be counted
                        $threeHundred = true;
                        $twoHundred = false;
                        $oneHundred = false;
                    }
                }
                if ($oneHundred) { //count number of lines in 100 section
                    $oneCount++;
                } else if ($twoHundred) { //count number of lines in 200 section
                    $twoCount++;
                } else if ($threeHundred) { //count number of lines in 300 section
                    $threeCount++;
                }
            }
            if ($oneCount > 0) { //if any lines are counted in 100 section cut the array from the beginning up to the number of counted rows
                $groupA = array_slice($group, 0, $oneCount, true); //cut the array and set it into a new array
                //Below is used for viewing the data
                //----------------------------------------------------
                /*echo "<hr>******************************<br>";
                var_dump($group);
                echo "<br>**************************<br><hr>";
                $i = 0;
                    echo "One:$oneCount<br>";
                    echo "<br>GroupA => <br>";
                foreach($groupA as $a) {
                    echo "$i. ";
                    var_dump($a);
                    echo "<br>";
                    $i++;
                }
                    echo "<hr><br>";*/
                //----------------------------------------------------
                $sortedData[$key][100] = $groupA; //set new array into another array with key values for name and code

            }
            if ($twoCount > 0) { //If any lines counted in 200 section cut the array from end of 100s to end of 200s
                $groupB = array_slice($group, $oneCount, $twoCount, true); //slice the array at designated points
                //Below is used for viewing the data
                //--------------------------------------------------
                /*echo "<hr>******************************<br>";
                var_dump($group);
                echo "<br>**************************<br><hr>";
                $i = 0;
                    echo "Two:$twoCount<br>";
                    echo "<br>GroupB => <br>";
                foreach($groupB as $b) {
                    echo "$i. ";
                    var_dump($b);
                    echo "<br>";
                    $i++;
                }
                    echo "<hr><br>";*/
                //----------------------------------------------------
                $sortedData[$key][200] = $groupB; //set cut array into another array with key values for name and code

            }
            if ($threeCount > 0) { //if any lines counted in 300 section cut the array from end of 200s to end of array
                $position = $oneCount + $twoCount;
                $groupC = array_slice($group, $position, $count, true); //slice the array at designated points
                //Below is used for viewing the data
                //---------------------------------------------------
                /*echo "<hr>******************************<br>";
                var_dump($group);
                echo "<br>**************************<br><hr>";
                $i = 0;
                    echo "Three:$threeCount<br>";
                    echo "<br>GroupC => <br>";
                foreach($groupC as $c) {
                    echo "$i. ";
                    var_dump($c);
                    echo "<br>";
                    $i++;
                }
                    echo "<hr><br>";*/
                //--------------------------------------------------
                $sortedData[$key][300] = $groupC; //set the cut array values into new array with key for name and code

            }
            $oneCount = $twoCount = $threeCount = 0; //set counters to 0 to reset for next iteration
        }

        //var_dump($sortedData);

        $balance = array();
        //Iterate through sections and add up debit and credit columns
        foreach($sortedData as $key => $value) {
            $debits = array();
            $credits = array();
            $sumDebit = 0.00;
            $sumCredit = 0.00;
            $loopCount = 0;
            //echo "Balance: $key => <br>";
            //for section 100
            if (array_key_exists(100, $value)) { //if the array key contains 100
                foreach ($value as $k => $data) {
                    if($k === 100) {
                        foreach ($data as $a) { //iterate through sections putting debits/credits into separate arrays
                            $debits[] = $a['debit'];
                            $credits[] = $a['credit'];
                            $loopCount++; //count iterations
                        }
                    }
                }
                $sumDebit = array_sum($debits); //sum the debits array
                $sumCredit = array_sum($credits); //sum the credits array
                //Below is used for viewing the data
                //---------------------------------------------------------------------
                /*echo "100 Debits: <br>";
                echo "Debits count: $loopCount <br>";
                var_dump($debits);
                echo "<br>Total: ";
                var_dump($sumDebit);
                echo "<br><br>";
                echo "100 Credits: <br>";
                echo "Credits count: $loopCount <br>";
                var_dump($credits);
                echo "<br>Total: ";
                var_dump($sumCredit);
                echo "<br><br>";*/
                //--------------------------------------------------------------------
                $balance[$key][100]['debitSum'] = $sumDebit; // set summed values into new array $balance with name and code as keys
                $balance[$key][100]['creditSum'] = $sumCredit; //set summed values into new array with name and code as keys
                $loopCount = 0;
                $debits = array();
                $credits = array();
            }
            //for section 200
            if (array_key_exists(200, $value)) {
                foreach ($value as $k => $data) {
                    if($k === 200) {
                        foreach ($data as $b) { //iterate through sections putting debits/credits into separate arrays
                            $debits[] = $b['debit'];
                            $credits[] = $b['credit'];
                            $loopCount++; //count iterations
                        }
                    }
                }
                $sumDebit = array_sum($debits); //sum the array
                $sumCredit = array_sum($credits); //sum the array
                //Below is used for viewing the data
                //-------------------------------------------------------------------
                /*echo "200 Debits: <br>";
                echo "Debits count: $loopCount <br>";
                var_dump($debits);
                echo "<br>Total: ";
                var_dump($sumDebit);
                echo "<br><br>";
                echo "200 Credits: <br>";
                echo "Credits count: $loopCount <br>";
                var_dump($credits);
                echo "<br>Total: ";
                var_dump($sumCredit);
                echo "<br><br>";*/
                //--------------------------------------------------------------------
                $balance[$key][200]['debitSum'] = $sumDebit; // set summed values into new array with name and code as key
                $balance[$key][200]['creditSum'] = $sumCredit;
                $loopCount = 0;
                $debits = array();
                $credits = array();
            }
            //for section 300
            if (array_key_exists(300, $value)) {
                foreach ($value as $k => $data) {
                    if($k === 300) {
                        foreach ($data as $c) {//iterate through sections putting debits/credits into separate arrays
                            $debits[] = $c['debit'];
                            $credits[] = $c['credit'];
                            $loopCount++;
                        }
                    }
                }
                $sumDebit = array_sum($debits); //sum the array
                $sumCredit = array_sum($credits); //sum the array
                //Below is used to view the data
                //--------------------------------------------------------------------
                /*echo "300 Debits: <br>";
                var_dump($debits);
                echo "Debits count: $loopCount <br>";
                echo "<br>Total: ";
                var_dump($sumDebit);
                echo "<br><br>";
                echo "300 Credits: <br>";
                echo "Credits count: $loopCount <br>";
                var_dump($credits);
                echo "<br>Total: ";
                var_dump($sumCredit);
                echo "<br><br>";*/
                //--------------------------------------------------------------------
                $balance[$key][300]['debitSum'] = $sumDebit; // set summed values into new array with name and code as key
                $balance[$key][300]['creditSum'] = $sumCredit;
                $loopCount = 0;
                $debits = array();
                $credits = array();
            }
            echo "<hr>";
        }
        //Used to view the data
        //------------------------------------------
        /*echo "<hr>Balance<br>";
        var_dump($balance);
        echo "<br><hr>";*/
        //------------------------------------------

        $output = array();
        //Prepare the data for export to index.php
        foreach($balance as $key => $value) {

            foreach ($value as $k => $v) {
                $dbt = $cdt = "";
                $dt = round($v['debitSum'], 2);
                //var_dump($dt);
                $ct = round($v['creditSum'], 2);
                //var_dump($ct);
                //echo "<br>Debit type: " . gettype($dt) . " | " . "Credit type: " . gettype($ct) . "<br>";
                //Determine if debit and credit are equal if so output line with data and html img tag
                if ($dt === $ct) {
                    $code = "| $k | Debit Total = $$dt | Credit Total = $$ct | <img src='images/checkmark-30x30.png' alt ='' height ='30' width='30'/> <br>";
                    $output[$key][$k]['balance'] = $code;
                } else { //if not subtract them and output the difference
                    if($dt > $ct){
                        $difference = round($dt - $ct, 2);
                        $dbt = "<span class='highlight'>Debit Total = $$dt</span>";
                        $cdt = "Credit Total = $$ct";
                    }
                    else{
                        $difference = round($ct - $dt, 2);
                        $dbt = "Debit Total = $$dt";
                        $cdt = "<span class='highlight'>Credit Total = $$ct</span>";
                    }
                    $code = "| $k | $dbt | $cdt | <span class='red'>$$difference</span> <br>";
                    $output[$key][$k]['notBalance'] = $code;
                }
            }
        }
        //set the array with the data and date into session variables
        $_SESSION['data'] = $output;
        $_SESSION['date'] = $date;

        //return to index.php
        header("Location: results.php");

        //close log
        fwrite($log, "Close Log --------------------------------------------------------------------------------" . PHP_EOL . PHP_EOL);
        fclose($log);


    } catch (Exception $e) {
        echo $e->getMessage();
        //header('Location: index.php?');
    }
}else{
    header('Location: index.php?output=<p>No File Was Selected</p>');
}
?>