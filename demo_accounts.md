# ApexCare Hospital Management System

## Demo Accounts

After importing the SQL database, run:

```
http://localhost/apexcare/seed_demo_accounts.php
```

This creates sample users for testing.

---

## Default Password

Every account uses:

```
Password123!
```

---

## Administrator Accounts

| Role | Username | Email | Password |
|------|----------|-------|----------|
| Super Admin | admin | admin@apexcare.com | Password123! |
| Admin | manager | manager@apexcare.com | Password123! |

---

## Doctor Accounts

| Name | Email |
|------|-------|
| Dr. John Mwangi | doctor1@apexcare.com |
| Dr. Sarah Chebet | doctor2@apexcare.com |

Password:

```
Password123!
```

---

## Receptionists

| Name | Email |
|------|-------|
| Grace Achieng | reception1@apexcare.com |
| Peter Kimani | reception2@apexcare.com |

Password:

```
Password123!
```

---

## Patients

| Name | Email |
|------|-------|
| James Otieno | patient1@apexcare.com |
| Mary Njeri | patient2@apexcare.com |
| David Kiptoo | patient3@apexcare.com |

Password:

```
Password123!
```

---

## Notes

The passwords are generated using PHP's `password_hash()` function, so they work with the built-in login system without requiring manual hashing.

For security, delete `seed_demo_accounts.php` after running it, or avoid deploying it to a production server.