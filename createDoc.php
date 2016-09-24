<?php
session_start();

function in_array_r($needle, $haystack){
    foreach($haystack as $array){
        if(in_array($needle,$array)){
            return true;
        }
    }
    return false;

}

/**********************************************************************************************************************
Author: Ryan Sloan
This process will read a sorted .csv F657 GL file from LCDN and analyze the credits and debits and whether or not
they balance and balance the General Ledger by making adjustments to the original file and output it's actions to
a web page.
ryan@paydayinc.com
 *********************************************************************************************************************/

/**
   Data Example:

Index: 0            1        2          3        4    5-Num   6   7   8-DB     9-CR      10-Name
PR061015 WK# 24, 99982473, ER WCA,   6/11/2015, 6508, 200,   20, 60,   2.3,       0,  AGNES L OLSEN
PR061015 WK# 24, 99982473, NM-SUI,   6/11/2015, 6510, 200,   20, 60,  5.47,       0,  AGNES L OLSEN
PR061015 WK# 24, 99982499, NETPAY,   6/11/2015, 1030, 100,    0,   0,    0,  483.07,  AMANDA  JARAMILLO
PR061015 WK# 24, 99982499, OASDI,    6/11/2015, 2210, 100,    0,   0,    0,   44.27,  AMANDA  JARAMILLO
PR061015 WK# 24, 99982499, ER OASDI, 6/11/2015, 2210, 100,    0,   0,    0,   44.27,  AMANDA  JARAMILLO
PR061015 WK# 24, 99982499, MEDICARE, 6/11/2015, 2220, 100,    0,   0,    0,   10.35,  AMANDA  JARAMILLO

**/
if(isset($_SESSION['fileData'])) {
    $fileData = $_SESSION['fileData'];
    $toBalance = $_SESSION['toBalance'];
    //var_dump($fileData);
    //var_dump($toBalance);
    $groups = array();
    foreach ($fileData as $k => $data) {
        $key = (int)$data[5];
        if ($key >= 200 && $key <= 265) {
            $key = 200;
        } else if ($key >= 300) {
            $key = 300;
        }
        //var_dump($data[10]. " " . $key);
        $data[11] = $k;
        $groups[$data[10]][$key][] = $data;
    }
    //var_dump($groups);
    foreach ($groups as $groupKey => &$group) {
        $bool = true;
        foreach ($group as $numKey => &$number) {
            $bool = in_array_r("NETPAY", $number);
            $test = in_array_r("NET PAYROLL", $number);
            if (!$bool && !$test) {
                $lineNumber = $number[count($number)-1][11];
                $number[] = array($number[0][0], $number[0][1], "NETPAY", $number[0][3], "1030", $number[0][5], $number[0][6], $number[0][7], "0.00", "0.00", $number[0][10],$lineNumber+1);
                //var_dump($number);
            }
        }
        unset($number);
    }
    unset($group);
    //var_dump($groups);
    $lines = array();
    foreach ($groups as $groupKey => $group) {
        if (array_key_exists($groupKey, $toBalance)) {
            foreach ($group as $numKey => $number) {
                //var_dump($groupKey. " " .$numKey);
                if (array_key_exists($numKey, $toBalance[$groupKey])) {
                    //var_dump("TRUE", $toBalance[$groupKey]);
                    $bool = true;
                    foreach ($number as $k => $array) {
                        if (($array[2] == "NETPAY" || $array[2] == "NET PAYROLL") && $bool == true) {
                            $lines[$groupKey][$numKey] = $array;
                            $bool = false;
                        }
                    }
                }
            }
        }
    }
    //var_dump($lines);
    $output = array();

    foreach ($lines as $lineKey => &$line) {
        foreach ($line as $numKey => &$number) {
            //var_dump($lineKey . " " . $numKey);
            //var_dump($lineKey, $toBalance[$lineKey]);
            //var_dump($number);
            $output[$lineKey][$numKey][] = "<b><u>". $lineKey. " - " . $numKey."</u></b>";
            $output[$lineKey][$numKey][] = "Adjustment to " . $number[2] . " Line " . ((int)$number[11] + 1);
            $dt = $toBalance[$lineKey][$numKey][0];
            $ct = $toBalance[$lineKey][$numKey][1];
            $difference = 0.00;
            //var_dump($lineKey . " " . $numKey);
            //var_dump($number);
            if ($dt > $ct) {
                $output[$lineKey][$numKey][] = "Previous Value: $" . $number[9];
                $difference = number_format(round($dt - $ct, 2), 2);
                $output[$lineKey][$numKey][] = "Difference: $" . $difference;
                $number[9] = (string)((float)$number[9] + $difference);
                $output[$lineKey][$numKey][] = "Current Value: <span class='currentVal'>$" . $number[9]. "</span>";

            } else {
                $output[$lineKey][$numKey][] = "Previous Value: $" . $number[9];
                $difference = number_format(round($ct - $dt, 2), 2);
                $output[$lineKey][$numKey][] = "Difference: -$" . $difference;
                $number[9] = (string)((float)$number[9] - $difference);
                $output[$lineKey][$numKey][] = "Current Value: <span class='currentVal'>$" . $number[9]. "</span>";
            }
            $output[$lineKey][$numKey][] = "<hr>";

            //var_dump($difference);
            //var_dump($number);

        }
        unset($number);

    }
    unset($line);
    //var_dump($lines);
    //var_dump($groups);
    foreach ($groups as $groupKey => &$group) {
        if (array_key_exists($groupKey, $toBalance)) {
            foreach ($group as $numKey => &$number) {
                //var_dump($groupKey. " " .$numKey);
                if (array_key_exists($numKey, $toBalance[$groupKey])) {
                    //var_dump("TRUE", $toBalance[$groupKey]);
                    $bool = true;
                    foreach ($number as $key => &$array) {
                        if (($array[2] == "NETPAY" || $array[2] == "NET PAYROLL") && $bool == true) {

                            $array = $lines[$groupKey][$numKey];
                            $bool = false;
                        }
                    }
                    unset($array);
                }
            }
            unset($number);
        }
    }
    unset($group);
    //var_dump("_____________________________________________________________________________________________",$groups);
    foreach($groups as $ee){
        foreach($ee as $number){
            foreach($number as $file){
                unset($file[11]);
                $newData[] = $file;
            }
        }

    }
    //var_dump($newData);
    //var_dump("OUTPUT___________________________________________________________________________________________",$output);




//Get todays date and time
    $today = new DateTime('now');
    $month = $today->format("m");
    $day = $today->format('d');
    $year = $today->format('y');
    $time = $today->format('H-i-s');


//Create a file name using todays date and current time
    $fileName = "processed/LCDN_Processed_File_" .$month . "-" . $day . "-" . $year . "-" . $time . ".csv";
    $handle = fopen($fileName, 'wb');

    //create a .csv from updated original fileData
    for($i = 0; $i < count($newData); $i++){
        fputcsv($handle, $newData[$i]);
        fwrite($handle, "\r\n");
    }

    fclose($handle);

    //assign the filename to the session for download using download.php
    $_SESSION['fileName'] = $fileName;

}else{
    echo "No Results Available";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>LCDN GL Balancer</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">

    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">

    <!-- Latest compiled and minified JavaScript -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <style>
        body{
            background-color: lightblue;
        }
        table{
            margin-left: auto;
            margin-right: auto;
            text-align: center;
            font-size: 18px;
        }
        h1, h2, h3, h4{
            text-align: center;
        }
        h1 {
            font-weight: bold;
        }
        #analysisDiv{

        }

        .heading{
            font-weight: bold;
            font-size: 18px;
        }
        .green{
            color: green;
        }
        .lineCount{
            text-align: center;
        }
        .currentVal{
            color: green;
        }
        td{
            padding-top: .5em;
            padding-bottom: .5em;
        }

    </style>
</head>
<body>
<header>
    <nav class="navbar navbar-default">
    <div class="container-fluid">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="index.php">Home</a>
        </div>

        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav">
                <li><a href="download.php">Download File</a></li>
                <li><a href="clear.php">Clear File</a></li>


            </ul>

            <ul class="nav navbar-nav navbar-right">

            </ul>
        </div><!-- /.navbar-collapse -->
    </div><!-- /.container-fluid -->
    </nav>
</header>
<main>
    <h1> LCDN GL BALANCER </h1>
    <h4> Below reflects the adjustments made to the original file</h4>
    <p class="lineCount"><?php echo "Line Count: " . count($newData); ?></p>
    <br>
    <hr>
    <div class="container-fluid">
        <div class="row">
            <div id="analysisDiv" class="col-md-12">
                <br>
                <?php
                //display the contents of output
                if(isset($output)){ //if session var is set
                    echo "<table>";
                   foreach($output as $ee){
                        foreach($ee as $array) {
                            foreach ($array as $line) {
                                echo "<tr><td>" . $line . "</td></tr>";
                            }
                        }
                    }
                    echo "</table>";

                }
                ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>