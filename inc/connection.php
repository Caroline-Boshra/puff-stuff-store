<?php 


$conn=mysqli_connect("localhost","root","","puff_stuff");


if ($conn->connect_error) {
    die(" connection failed: " . $conn->connect_error);
}