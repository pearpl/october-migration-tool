
# OctoberCMS Migration Tool

A standalone PHP script to help you easily **migrate your OctoberCMS project** from one server to another.

Created by **AlienatedAlien**, [Pear Interactive](https://www.pear.pl).

---

## Features

- One-click site ZIP archive (excluding cache/resized/temp folders)
- Unpack a `.zip` archive into the project directory
- Generate MySQL database dump
- Password-protected interface
- Version compatibility check (PHP & CLI) using stored JSON
- Optional: Use phpMyAdmin/CLI for DB dump if built-in fails

---

## Usage Instructions

1. **Upload the script**
   - Upload `migration_tool.php` into a `migration/` folder within your OctoberCMS root using FTP or SSH.

2. **Set a password**
   - Open the script and change the default password:
     ```
     $access_password = "migration2025"; // <-- Change this!
     ```

3. **Run the tool on your current (source) server**
   - Visit `https://yourdomain.com/migration/migration_tool.php`
   - Log in using your set password
   - Perform a **PHP version check** – the script will store a `php_versions.json` file that captures PHP and CLI versions of the current environment.

4. **Zip the site and export the DB**
   - Use the interface to create a full zip and database dump.

5. **Transfer to the new server**
   - Upload the zip archive, SQL dump, and `php_versions.json` to the new server.

6. **Run the tool on the new server**
   - Use it to compare PHP/CLI versions with the original environment for compatibility verification.

7. **Final migration steps**
   - Edit the `.env` file to update **database connection details** (host, username, password, database name).
   - Import the SQL database using **phpMyAdmin**, or **command line**.
   - If you're using the **Deploy plugin**, remember to **update your Beacon or endpoint URL** in the plugin settings.

8. **Database dump notice**
   - The built-in MySQL dump tool uses PHP's PDO. It works on most servers, but **may not function** if:
     - Your MySQL user lacks permission to access all tables.
     - The server has restrictions on database operations from PHP.
   - If the dump fails, use **phpMyAdmin**, or the **command line** to export the database manually.

9. **Clean up**
   - For security, **delete the `migration/` folder** once you're done.

---

## Security Notice

This script uses basic session-based password protection. **Never leave it online after use**.

Delete the script and any generated backups after migration is complete.

---

## ☕ Support the Developer

If this saved you time or stress, consider [buying me a coffee](https://buymeacoffee.com/alienatedalien). Every bit of support helps keep more open tools like this coming.

---

## License

MIT License — free to use, modify, and redistribute.

---

## Disclaimer

Use this script at your **own risk**. It is provided “as is” without warranty of any kind. The script **does not remove or delete any files** by itself, but misuse or misconfiguration may lead to unintended consequences. The author takes **no responsibility** for any data loss, misbehavior, or damage caused by using this tool.

Always test and backup your project before running operations on a live environment.
