<?php
session_start();
$fileData = $_SESSION['fileData'];

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

//Assign the values of the first name and number
$number = $fileData[0][5];
$name = $fileData[0][10];

//set up netpay variable
$netpay = false;

//create arrays
$newArray = array();
$lineNumbers = array();

//create array to hold line numbers of netpay lines
$npLineNumbers = array();

//create arrays for processing data from the file
$values = array();
$insertLine = array();

//For output purposes
/**********************************************************************************************************/
/*echo "Count: FileData " . count($fileData) . "<br><hr>";
for($i = 0; $i < count($fileData); $i++){
    echo "$i => ";
    var_dump($fileData[$i]);
    echo "<br><br><hr>";
}*/


//Iterate through $fileData
for($i = 0; $i < count($fileData); $i++){
    //demarcate where name changes and reset name and number
    if($fileData[$i][10] !== $name) {
        $name = $fileData[$i][10];
        $number = $fileData[$i][5];
        //if netpay is not set to true by a netpay line this line saves the line number and data for that person to use later in adding a netpay line
        if($netpay === false){ //if iteration encounters a "netpay line" it will skip this step if not it will execute
            $insertLine[] = $i; //line number to insert into
            $values[] = array( array($fileData[$i-1][0], $fileData[$i-1][1], "NETPAY", $fileData[$i-1][3], $fileData[$i-1][4], $fileData[$i-1][5],
                $fileData[$i-1][6], $fileData[$i-1][7], 0.00, 0.00, $fileData[$i-1][10], 'INSERTED')); //values from the person before
            $prevName = $fileData[$i-1][10];
            $npLineNumbers[$prevName][$number] = $i;
        }
        $netpay = false;
    }
    //demarcate where number changes and add line number to lineNumbers array
    if($fileData[$i][5] !== $number) {
        $number = $fileData[$i][5];
        $name = $fileData[$i][10];
    }
    //notate the line numbers of the final net pay line per employee per number section
    if($fileData[$i][2] === 'NETPAY' || $fileData[$i][2] === 'NET PAYROLL') {
        $npLineNumbers[$name][$number] = $i;
        $netpay = true;
    }

    //Add the line to a reorganized associative array that groups data by name and number
    $newArray[$name][$number][] = $fileData[$i];
}

for($i = 0; $i < count($insertLine); $i++) {

    array_splice($fileData, $insertLine[$i], 0, $values[$i]);

    $npLineNumbers[$name][$number] = $insertLine[$i];
}

echo "Netpay Line Counts: " . count($npLineNumbers) . "<br>";
//For output purposes
//******************************************************************************************************
/*for($i = 0; $i < count($fileData); $i++){
    echo "$i => ";
    var_dump($fileData[$i]);
    echo "<br>";
}*/


$q=1;
foreach($npLineNumbers as $name => $data){
    echo "$q : $name => ";
    echo count($data) . "<br>";
    foreach($data as $number => $row){
        echo "$number => ";
        var_dump($row);
        echo "<br>";
    }
    echo "<br>";
    $q++;
}

//assign netpay line numbers from associative to indexed array
foreach($npLineNumbers as $name => $data) {
    foreach ($data as $number => $row) {
        $lineNumbers[] = $row;
    }
}

echo "Line Numbers Count: " . count($lineNumbers) . "<br>";
//For output purposes
//*****************************************************************************
/*echo "<br>fileData before adjustments <br><hr>";
foreach($fileData as $key => $data){
    echo $data[10]. " ";
    echo "$key => ";
    var_dump($data[8]);
    echo "   ";
    var_dump($data[9]);
    echo "   ";
    var_dump($data[2]);
    echo "<br>";
}
echo "<hr>";*/

/*echo "<br>Line Numbers<br><hr>";
for($i = 0; $i < count($lineNumbers); $i++)
{
    var_dump($lineNumbers[$i]);
    echo "<br>";
}
echo "<br><hr>";*/

/*
$o = 0;
echo "Reorganized Data Set<br><hr>";
foreach($newArray as $name => $data){
    echo "$name => <br>";
    foreach($data as $number => $row){
        echo "Index: $number => <br>";
        foreach($row as $line) {
            echo "Line: $o ->";
            var_dump($line[5]);
            echo "  ";
            var_dump($line[10]);
            echo "  ";
            var_dump($line[2]);
            echo "<br>";
            $o++;
        }
        echo "<br>";
    }
    echo "<br><hr>";
}
*/
//********************************************************************************

//Create an array to hold information about data
$sortedInfo = array();
//Iterate through reorganized array
foreach($newArray as $name => $data){
    //echo "$name => <br>";
    foreach($data as $number => $row){
        //echo "$number => <br>";
        $sumDebit = $sumCredit = $difference = 0.00;
        foreach($row as $index => $line){ //add group lines for debit and credit columns
           //echo "$index => ";
            $sumDebit += $line[8];
            $sumCredit += $line[9];
        }
        $sumDebit = round($sumDebit, 2); //round to 2 places
        $sumCredit = round($sumCredit, 2);
        if($sumDebit === $sumCredit){ //if debit and credit are equal
            $difference = 0.00;       // set variables for later evaluation
            $balanced = true;
            $debitGreater = null;
        }
        else if($sumDebit > $sumCredit){ //if debit is greater than credit
            $difference = $sumDebit - $sumCredit; //set variables for later evaluation
            $balanced = false;
            $debitGreater = true;
        }
        else{
            $difference = $sumCredit - $sumDebit; //else set variables for later evaluation
            $balanced = false;
            $debitGreater = false;
        }
        //insert an array with all values into sortedInfo associative array
        $sortedInfo[] = array("balanced" => $balanced, "debitBal" => $sumDebit,
            "creditBal" => $sumCredit, "difference" => round($difference,2), "debitGreater" => $debitGreater,
            "name" => $name, 'number' => $number);

    }

}

echo "Count SortedArray : ". count($sortedInfo) . "<br>";

/*for($i = 0; $i < count($sortedInfo); $i++) {
    echo "$i => ";
    var_dump($sortedInfo[$i]);
    echo "<br>";
}*/

$r=0;
foreach($npLineNumbers as $name => $data){
    echo "$r : $name => ";
    echo count($data) . "<br>";
    foreach($data as $number => $row){
        echo "$number => ";
        var_dump($row);
        echo "<br>";
        var_dump($sortedInfo[$r]);
        echo "<br>";
        $r++;
    }
    echo "<br><hr>";

}

//create an array to hold the formulated output
$output = array();

//Iterate through lineNumbers array
for($i = 0; $i < count($lineNumbers); $i++) {
    if ($sortedInfo[$i]['balanced'] === false) { //for those groups that did NOT balance
        if ($sortedInfo[$i]['debitGreater'] === true) { //where the debit value is greater than the credit value
            $diff = $sortedInfo[$i]['difference']; //assign the difference
            $fileData[$lineNumbers[$i]][9] += $diff; //add it directly to the line value in the original file
            $credBal = $sortedInfo[$i]['creditBal']; //assign credit balance
            $debBal = $sortedInfo[$i]['debitBal']; //assign debit balance
            $total = $credBal + $diff; //add difference to credit balance for output
            $line = $lineNumbers[$i]; //assign the line number
            $name = $sortedInfo[$i]['name']; //assign the name associated with the line
            $number = $sortedInfo[$i]['number']; //assign the number (200) assigned with the line
            //create new array with all the needed values and assign to output array
            $output[] = array("name" => $name, "number" => $number,
                "lineNum" => "Line: $line", "output" => "Added <span class='green'>$$diff</span> to Credit Column Balance $$credBal",
                "total" => "New Credit Balance = $$total", "oldCreditBal" => $credBal, "oldDebitBal" => $debBal, "debit" => false);
        } else {
            $diff = $sortedInfo[$i]['difference']; //assign the difference
            $fileData[$lineNumbers[$i]][8] += $diff; //add the difference directly to the line value in the original file
            $debBal = $sortedInfo[$i]['debitBal']; //assign debit bal
            $credBal = $sortedInfo[$i]['creditBal'];//assign credit bal
            $total = $debBal + $diff; //add difference to debit balance for output
            $line = $lineNumbers[$i]; //assign the line number
            $name = $sortedInfo[$i]['name']; //assign the name associated with the line
            $number = $sortedInfo[$i]['number'];//assign the number associated with the line
            //create an array with all the needed values and assign to output array
            $output[] = array("name" => $name, "number" => $number,
                "lineNum" => "Line: $line", "output" => "Added <span class='green'>$$diff</span> to Debit Column Balance $$debBal",
                "total" => "New Debit Balance = $$total", "oldDebitBal" => $debBal, "oldCreditBal" => $credBal, "debit" => true);
        }
    }
}
//set $output to the session
$_SESSION['output'] = $output;

//For output purposes to view data
//****************************************************************************
/*echo "fileData after adjustments <br><hr>";
foreach($fileData as $key => $data){
    echo $data[10];
    echo "$key => ";
    var_dump($data[8]);
    echo "   ";
    var_dump($data[9]);
    echo "<br>";
}
echo "<hr>";*/
//****************************************************************************

//Get todays date and time
$today = new DateTime('now');
$month = $today->format("m");
$day = $today->format('d');
$year = $today->format('y');
$time = $today->format('H:i:s');

//Create a file name using todays date and current time
$fileName = "processed/LCDN_Processed_File_" .$month . "-" . $day . "-" . $year . "-" . $time . ".csv";
$handle = fopen($fileName, 'w');

//create a .csv from updated original fileData
for($i = 0; $i < count($fileData); $i++){
    fputcsv($handle, $fileData[$i], ",");
    //fwrite($handle, "\r\n");

}

fclose($handle);

//assign the filename to the session for download using download.php
$_SESSION['fileName'] = $fileName;

if(isset($_SESSION['lineCount'])){
    $lineCount = "Line Count: " .$_SESSION['lineCount'];
}
else{
    $lineCount = "";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>LCDN GL Balancer</title>
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
        }
        h1{
            text-align: center;
        }
        h4{
            text-align: center;
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
    <p class="lineCount"><?php echo $lineCount; ?></p>
    <br>
    <hr>
    <div class="container-fluid">
        <div class="row">
            <div id="analysisDiv" class="col-md-12">
                <br>
                <?php
                //display the contents of output
                if(isset($_SESSION['output'])){ //if session var is set
                    $display = $_SESSION['output']; //assign
                    //var_dump($docOutput);
                    foreach($display as $index => $data){
                        //assign and output all elements of each individual group
                        $name = $data['name'];
                        $number = $data['number'];
                        $lineNum = $data['lineNum'];
                        $msg = $data['output'];
                        $total = $data['total'];
                        $debBal = $data['oldDebitBal'];
                        $credBal = $data['oldCreditBal'];
                        $oldBal = ($data['debit'] === true) ? "Credit Balance = $$credBal" : "Debit Balance = $$debBal";
                        $var = ($data['debit'] === true) ? " Debit Adjustment" : "Credit Adjustment";
                        echo "<table>";
                        echo "<tr><td><span class='heading'>$name</span></td></tr>";
                        echo "<tr><td><p><span style='font-size: 18px;'>$number</span></p></td></tr>";
                        echo "<tr><td><p>$var | $lineNum</p></td></tr>";
                        echo "<tr><td><p>| $msg |</p></td></tr>";
                        echo "<tr><td><p>| $total | $oldBal |</p></td></tr>";
                        echo "</table><br><hr>";
                    }

                }
                ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>