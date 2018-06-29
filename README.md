# G2Preinstaller
A very small tool that puts Gallery 2 on your webserver
Installing Gallery 2 on your webserver is as simple as:
# Download the preinstaller and put a single file on your webserver
# After two clicks Gallery 2 is ready on your server to be configured.

This is a great alternative for those users who do not have the option of extracting .zip or .tar files directly on the webserver. Our advice would be to [http://galleryproject.org/wiki/Web_Hosting_Referral_Page switch to a better webhoster] in such a case: better doesn't always mean more expensive. But if you want or need to stick with your current webhost that only offers FTP access or another simple web-transfer method, you should definitely use the Pre-Installer since uploading all Gallery 2 files and directories can take more than an hour and is very error-prone when using a bad FTP connection.

'''Note:''' If you use the Pre-Installer to get Gallery 2 onto your webserver, all your files and folders will be owned by the webserver user and not by your account on the webserver. This complicates upgrading to future Gallery 2 versions a little bit, but Gallery 2 ships with an easy to use tool that allows you to resolve that problem with a single click.

'''Download: [[Gallery2:Download#Packages|Download Now!]]'''

== Installation Instructions  - Usage ==
And here's the whole truth...all steps in detail:
# Download the Gallery 2 Pre-Installer to your computer. Get it [[Gallery2:Download#Packages|here]]
# Extract the gallery2-preinstaller-1.0.zip file.
# Open the extracted preinstall.php in Wordpad, notepad or another texteditor.
# Enter a password at the top of the file.
# Upload preinstall.php via FTP or another method to your webserver, e.g. to <nowiki>http://www.yourwebsite.com/preinstall.php</nowiki>
# Use your FTP program to change the permissions of the folder where preinstall.php is in to 777 (read+write+execute permission for everybody).
# Browse with your webbrowser to the location where you have uploaded preinstall, in our example that would be <nowiki>http://www.yourwebsite.com/preinstall.php</nowiki>
# Enter the password that you just added to preinstall.php in the web form.
# Click the download button to transfer the latest version of Gallery 2 to your webserver. Depending on your webserver this step can take only a second or up to ~15 minutes.
# Click the extract button to extract the Gallery 2 archive directly on the webserver. This step can take a few minutes.
# Follow the link to the Gallery 2 installer which will guide you through the storage folder and database setup steps.
# For security reasons, don't forget to change the permissions of the folder where preinstall.php is in back to 755 (read+exectute for everyone, read+write+execute for the owner). 

'''Note:''' In the Gallery installer (The 11 step wizard which you go through once you are finished with the Pre-Installer), it is very important that you choose a Gallery storage folder that is outside of your gallery2 folder. It makes your life a lot easier!

== Upgrading ==
=== Upgrading a normal installation using the Pre-Installer ===
# With your FTP program, move your gallery folder to e.g. gallery_old.
# Use the preinstall.php script to get G2 onto your webserver.
# If necessary, use the preinstall.php script to rename your gallery2 folder to e.g. gallery.
# Copy config.php and .htaccess to your new gallery folder.
# Run the upgrader as usual (browse to your gallery, it will start automatically).

=== Upgrading a G2 that has been installed with the Pre-Installer ===
# Use the chmod tool of your G2 (<nowiki>http://www.example.com/gallery2/lib/support/index.php?chmod</nowiki>) to prepare your gallery folder for the upgrade (open up the filesystem permissions). 
# Put the preinstall.php script into the same folder as your Gallery folder is in (not into gallery2/main.php. Put it one folder higher.)
# If your Gallery folder is not "gallery2", use the preinstall.php script to rename your old Gallery folder to gallery2
# Use the preinstall.php script to get the latest G2 version onto server. (Download)
# Use the preinstall.php script to extract the new version over your existing gallery2 folder.
# Use preinstall.php to rename gallery2 to your original folder name, if you had a different name.
# Delete preinstall.php (leaving it on your server poses a security risk).
# Run the upgrader as usual (browse to your gallery, it will start automatically).
# Use the chmod tool (lib/support/) to secure your Gallery folder again. Maybe also click "fix Gallery Storage folder" after securing the Gallery folder.

=== Switching from a G2 that has been installed with the Pre-Installer to a normal installation ===
# Use the chmod tool of your G2 (<nowiki>http://www.example.com/gallery2/lib/support/index.php?chmod</nowiki>) to prepare your gallery folder for the upgrade (open up the filesystem permissions). 
# Rename your Gallery folder to something like gallery_old
# Put your new Gallery folder on the webserver with whatever non-Pre-Installer method (FTP, CVS, ssh, ...) since you want to get rid of a "server-owned" G2.
# Copy config.php and .htaccess from your old to your new Gallery folder.
# Maybe the new Gallery folder is also a new version of G2, if so, run the upgrader.
# Make sure everything is working when using your new folder.
# If so, you can now safely delete your old gallery_old folder.

== FAQ == 
=== Which download method should I choose? ===
All of them work. If available, choose Curl or wget since they are pretty efficient. Fsockopen and fopen on the other hand are the better job if the download takes a long time on your server and if wget and cURL time out for you.

=== Which extract method should I choose? ===
If available, go with any of the zip methods or with the PHP based tar. The tar binary could have some problems with very long paths.

=== Where is the chmod tool in Gallery 2? ===
The tool to change file permissions (change mode or short "chmod") is in <nowiki>http://www.example.com/gallery2/lib/support/index.php?chmod</nowiki>. Note: Only G2.1.1 and later have the chmod script built in.
Also see: [[Gallery2:FAQ#How_can_I_fix_the_filesystem_permissions_of_the_Gallery_storage_folder.3F|How can I fix the filesystem permissions of the Gallery storage folder?]].

=== How can I upload a theme or module via FTP when I used the preinstaller? ===
See: G2 FAQ: [[Gallery2:FAQ#How_can_I_upload_a_theme_or_module_via_FTP_when_I_used_the_preinstaller.3F|How can I upload a theme or module via FTP when I used the preinstaller?]]

=== The chmod tool does not work, what's wrong? ===
The chmod tool can only change the filesystem permissions of files and folders that are owned by the webserver. Probably, some or all of your files / folders are owned by your account. If you don't use the Pre-Installer anyway, just forget about the chmod tool. Probably you don't need it. If you are a Pre-Installer user and need it to work, ask your webhost to run 'chown -R www /path/to/your/gallery/' since maybe your webhost accidentally changed the owner when restoring a backup or because they thought that was the right thing to do.
