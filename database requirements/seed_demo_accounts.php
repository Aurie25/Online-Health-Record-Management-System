<?php
/*
======================================================
 ApexCare Hospital Management System
 Demo Account Seeder

 Run once after importing the database.

 URL:
 http://localhost/apexcare/seed_demo_accounts.php

 Delete this file after running.
======================================================
*/

require_once "db.php";

echo "<h2>ApexCare Demo Account Seeder</h2>";

try {

    $conn->beginTransaction();

    /*
    =====================================
    Clear existing demo data (optional)
    =====================================
    */

    $conn->exec("DELETE FROM doctor_profiles");
    $conn->exec("DELETE FROM admins");
    $conn->exec("DELETE FROM users");

    /*
    =====================================
    PASSWORD
    =====================================
    */

    $password = password_hash("Password123!", PASSWORD_DEFAULT);

    /*
    =====================================
    ADMINS
    =====================================
    */

    $stmt = $conn->prepare("
        INSERT INTO admins
        (full_name,username,email,password,role)
        VALUES
        (?,?,?,?,?)
    ");

    $stmt->execute([
        "System Administrator",
        "admin",
        "admin@apexcare.com",
        $password,
        "super_admin"
    ]);

    $stmt->execute([
        "Hospital Administrator",
        "manager",
        "manager@apexcare.com",
        $password,
        "admin"
    ]);

    /*
    =====================================
    USERS
    =====================================
    */

    $user = $conn->prepare("
        INSERT INTO users
        (
            first_name,
            last_name,
            email,
            national_id,
            phone,
            date_of_birth,
            gender,
            password,
            role
        )
        VALUES
        (?,?,?,?,?,?,?,?,?)
    ");

    /*
    ----------------------------
    Doctor 1
    ----------------------------
    */

    $user->execute([
        "John",
        "Mwangi",
        "doctor1@apexcare.com",
        "10000001",
        "0711000001",
        "1985-05-12",
        "Male",
        $password,
        "doctor"
    ]);

    $doctor1 = $conn->lastInsertId();

    /*
    Doctor 2
    */

    $user->execute([
        "Sarah",
        "Chebet",
        "doctor2@apexcare.com",
        "10000002",
        "0711000002",
        "1988-09-18",
        "Female",
        $password,
        "doctor"
    ]);

    $doctor2 = $conn->lastInsertId();

    /*
    Receptionist 1
    */

    $user->execute([
        "Grace",
        "Achieng",
        "reception1@apexcare.com",
        "10000003",
        "0711000003",
        "1995-03-01",
        "Female",
        $password,
        "receptionist"
    ]);

    /*
    Receptionist 2
    */

    $user->execute([
        "Peter",
        "Kimani",
        "reception2@apexcare.com",
        "10000004",
        "0711000004",
        "1994-10-12",
        "Male",
        $password,
        "receptionist"
    ]);

    /*
    Patients
    */

    $patients = [

        ["James","Otieno","patient1@apexcare.com","10000005","0711000005","1999-01-01","Male"],

        ["Mary","Njeri","patient2@apexcare.com","10000006","0711000006","1997-06-16","Female"],

        ["David","Kiptoo","patient3@apexcare.com","10000007","0711000007","2001-11-20","Male"]

    ];

    foreach($patients as $p){

        $user->execute([
            $p[0],
            $p[1],
            $p[2],
            $p[3],
            $p[4],
            $p[5],
            $p[6],
            $password,
            "patient"
        ]);

    }

    /*
    =====================================
    DOCTOR PROFILES
    =====================================
    */

    $profile = $conn->prepare("
        INSERT INTO doctor_profiles
        (
            doctor_id,
            specialization,
            qualifications,
            experience_years,
            consultation_fee,
            status,
            bio
        )
        VALUES
        (?,?,?,?,?,?,?)
    ");

    $profile->execute([
        $doctor1,
        "General Medicine",
        "MBChB",
        10,
        2500,
        "working",
        "General physician."
    ]);

    $profile->execute([
        $doctor2,
        "Cardiology",
        "MBChB, MMed Cardiology",
        8,
        4500,
        "working",
        "Consultant cardiologist."
    ]);

    $conn->commit();

    echo "<h3 style='color:green;'>Demo accounts created successfully.</h3>";

    echo "<p><strong>Delete this file after running it.</strong></p>";

}
catch(Exception $e){

    $conn->rollBack();

    echo "<h3 style='color:red'>".$e->getMessage()."</h3>";

}
?>