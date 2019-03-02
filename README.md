# PHCDownload v1.1.2 (Manual installation)

##### Originally released in 2008 - Thought I'd share the last release of my first ever project.
##### Given the age of this project, I wouldn't reccomend using it for anything important! Strictly no warranty here!

Only use this method if you have not been able to install using
 1. Copy the contents of the src directory to your server.
2. Import the contents of MANUAL.SQL into your MySQL database. Make a note of
    the database name, user name, and password assigned to the database. Also,
    make a note f the server host (if different from LOCALHOST), and the
    server port (if different from 3306) to add to the CONFIG.INI.PHP later.

3. Go to the directory where you have uploaded the software files, browse to
    the INCLUDE folder and open the CONFIG.INI.PHP file for editing.

 4. Add the database information that you noted earlier to the relevant as
    labeled below (do not change the DB_TABLE_PREFIX field):

       `db_host, db_username, db_password, db_database, db_port`

 5. Save the CONFIG.INI.PHP and access the software control panel using a web
    browser (see example below):

       E.g: http://www.example.com/phcdownload/admin/index.php

    (If you see a DATABASE ERROR message, adjust settings as detailed.)

 6. If you see a login page, use the follow details to access the control
    panel (values below are case-sensitive):

       `Username: Administrator
       Password: password`
       
    Remember to change the login details for the Administrator account!!

 7. YOUR DONE!
