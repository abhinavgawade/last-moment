-- Replace 'your_email@example.com' with the email of the user you want to make admin
UPDATE users SET role = 'admin' WHERE email = 'your_email@example.com';
