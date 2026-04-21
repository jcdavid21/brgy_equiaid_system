<?php 
    require_once("db.php");

    $password = "admin123";
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $pdo->exec("UPDATE users SET password = '$hashed_password'");
    
    if ($pdo->errorCode() === '00000') {
        echo "Passwords updated successfully.";
    } else {
        echo "Error updating passwords: " . implode(" ", $pdo->errorInfo());
    }
?>