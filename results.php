<?php
/**********************************************************************************************************************
Author: Ryan Sloan
This page will output the results of processor.php to a web page
ryan@paydayinc.com
 *********************************************************************************************************************/
session_start();

if(isset($_SESSION['data'])) {
    $output = $_SESSION['data'];
    $date = $_SESSION['date'];
    $createDoc = "<a href='createDoc.php' class='balanceDoc' target='_blank'>Balance Document</a>";
    $clear = '<a href="clear.php" class="link">Clear File</a>';
}
else{
    $output = null;
    $date = null;
    $createDoc = "";
    $clear = "";
}

if(isset($_SESSION['output'])){
    $link = "<a href='createDoc.php' target='_blank'>Results</a>";
    $download = '<a href="download.php">Download File</a>';
}
else{
    $link = "";
    $download = "";
}

?>
<!DOCTYPE>
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
        }
        td{
            padding: 3px;
        }
        h1{
            text-align: center;
        }
        h4{
            text-align: center;
        }
        .container-fluid{
            display: block;
        }
        #resultsDiv{
            text-align: center;
            width: 100%;
            /*border: 1px solid blue;*/
            margin-left: auto;
            margin-right: auto;
            padding: 15px;
        }
        .heading{
            font-weight: bold;
            font-size: 18px;
        }
        .red{
            color: red;
        }
        .green{
            color: green;
        }
        .highlight{
            background-color: cadetblue;
            padding: 3px;
        }
        .border{
            text-decoration: underline;
        }
        .balanceDoc{
            text-decoration: none;
        }
        .link{
            color: blue;
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
                    <li><?php echo $createDoc; ?></li>
                    <li><?php echo $link; ?></li>
                    <li><?php echo $download; ?></li>
                    <li><?php echo $clear; ?></li>


                </ul>

                <ul class="nav navbar-nav navbar-right">

                </ul>
            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</header>
<main>
    <h1> LCDN GL BALANCER </h1>
    <h4> Below is the analysis of the submitted F657 GL File</h4>
    <div class="container-fluid">
        <div class="row">
            <div id="resultsDiv" class="col-md-12">
                <?php
                if(isset($output)) {
                    echo "<span class='heading border'>File Date: $date</span><br><br>";
                    foreach ($output as $key => $data) {
                        echo "<span class='heading'>$key</span><br>";
                        foreach ($data as $array) {
                            foreach ($array as $line) {
                                echo "<p>$line</p>";
                            }
                        }
                        echo "<br><hr>";
                    }
                }
                ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>