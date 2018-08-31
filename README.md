# The Gallery 2 Pre-Installer

## A Tiny Tool to Put Gallery 2 on your Webserver

Installing Gallery 2 with the preinstaller on your webserver is as simple as:

1.  Download the Gallery 2 Pre-Installer and put a single file on your webserver
2.  After two clicks, Gallery 2 is ready to be configured.

This is a great alternative for users who do not have the option of extracting .zip or .tar files directly on the webserver.

**Note:** If you use the Gallery 2 Pre-Installer to get a Gallery 2 Instance onto your webserver, your files and folders **MAY** be owned by the _webserver user_ and not by your account on the webserver. This can complicate upgrading to future versions but Gallery 2 ships with an easy to use tool that allows you to resolve this with a single click.

[Download Now!](https://github.com/dakanji/G2Project-preinstaller/releases)

## Installation Instructions - Usage

1.  Download the Gallery 2 Pre-Installer to your computer. [GET IT HERE](https://github.com/dakanji/G2Project-preinstaller)
2.  Extract the Gallery 2 Pre-Installer zip file.
3.  Open the extracted preinstall.php in Wordpad, notepad or another texteditor.
4.  Enter a passphrase at the top of the file.
5.  Upload preinstall.php via FTP or another method to your webserver, e.g. to _http://www.example.com/preinstall.php_
6.  Use your FTP program to change the permissions of the folder where preinstall.php is in to _777_ (read+write+execute permission for everybody).
7.  Browse with your webbrowser to the location where you have uploaded preinstall.php. In our example, that would be _http://www.example.com/preinstall.php_
8.  Enter the password that you just added to preinstall.php in the web form when prompted.
9.  Follow the on screen instructions.

## Upgrading

### Upgrading a normal installation using the Gallery 2 Pre-Installer

1.  With your FTP program, move your Gallery 2 folder to e.g. gallery_old.
2.  Use the preinstall.php script to get a Gallery 2 Instance onto your webserver.
3.  If necessary, use the preinstall.php script to rename your Gallery 2 folder to e.g. gallery.
4.  Copy config.php and .htaccess to your new gallery folder.
5.  Run the upgrader as usual (browse to your gallery, it will start automatically).

### Upgrading a Gallery 2 Instance Installed with the Gallery 2 Pre-Installer

1.  Use the chmod tool of your Gallery 2 Instance (_http://www.example.com/gallery2/lib/support/index.php?chmod_) to prepare your gallery folder for the upgrade (open up the filesystem permissions).
2.  Put the preinstall.php script into the same folder as your Gallery 2 Instance (not into _gallery2/main.php_. Put it one folder higher.)
3.  If your Gallery folder is not _gallery2_, use the preinstall.php script to rename your old Gallery folder to Gallery 2
4.  Use the preinstall.php script to get the latest Gallery 2 version onto server. (Download)
5.  Use the preinstall.php script to extract the new version over your existing gallery2 folder.
6.  Use preinstall.php to rename _gallery2_ to your original folder name, if you had a different name.
7.  Delete preinstall.php (leaving it on your server poses a security risk).
8.  Run the upgrader as usual (browse to your gallery, it will start automatically).
9.  Use the chmod tool (lib/support/) to secure your Gallery 2 folder again. Maybe also click _fix Gallery Storage folder_ after securing the Gallery folder.

### Switching from a Gallery 2 Instance Installed with the Gallery 2 Pre-Installer to a Standard Installation

1.  NB: You can skip these instructions if you are running PHP-CGI or PHP-FPM. With these PHP modes, your site will already have been configured ready to accept updates.
2.  Use the chmod tool of your Gallery 2 Instance (_http://www.example.com/gallery2/lib/support/index.php?chmod_) to prepare your gallery folder for the upgrade (open up the filesystem permissions).
3.  Rename your Gallery 2 folder to something like "gallery_old"
4.  Put your new Gallery 2 folder on the webserver with whatever _non-Pre-Installer_ method since you want to get rid of a _server-owned_ Gallery 2 Instance.
5.  Copy config.php and .htaccess from your old to your new Gallery 2 folder.
6.  If the new Gallery 2 folder is also a new version of Gallery 2, run the upgrader.
7.  Make sure everything is working when using your new folder.
8.  If so, you can now safely delete your old _gallery_old_ folder.

## FAQ

### Which transfer method should I choose?

All of them work. If available, choose _Curl_ or _wget_ since they are pretty efficient. Fsockopen and fopen on the other hand are better if the transfer takes a long time and wget and cURL time out for you.

### Which extract method should I choose?

If available, go with any of the zip methods or with the PHP based tar. The tar binary could have some problems with very long paths.

### Where is the chmod tool in Gallery 2?

The tool to change file permissions (_change mode_, or in short, _chmod_) is in _http://www.example.com/gallery2/lib/support/index.php?chmod_.

Also see: [How can I fix the filesystem permissions of the Gallery storage folder?](#how-can-i-fix-the-filesystem-permissions-of-the-gallery-storage-folder)

### How can I upload a theme or module via FTP when I used the Gallery 2 Pre-Iinstaller?

If you installed Gallery 2 with the Gallery 2 Pre-Installer, you need to first open the _modules/_ and _themes/_ directory for access before you can upload a theme or module manually yourself.

1.  Go to your site, sign in with your Gallery 2 setup password and follow the _Filesystem Permissions_ link.
2.  Click on "Add a new module or theme (make modules/ and themes/ writeable)" to open up your _themes/_ and _modules/_ folders.
3.  Now you can upload your theme or module via FTP.
4.  Finally do not forget to _close_ the _themes/_ and _modules/_ folders again on the "Filesystem Permissions" page.

*   Note that you can fetch new modules and themes directly via the Site Admin -> Plugins page. FTP is not needed unless for modules and themes that are not in the available repositories.

### The chmod tool does not work, what's wrong?

The chmod tool can only change the filesystem permissions of files and folders that are owned by the webserver. Probably, some or all of your files / folders are owned by your account. If you don't use the Gallery 2 Pre-Installer anyway, just forget about the chmod tool. Probably you don't need it.

If you are a Gallery 2 Pre-Installer user and need it to work, ask your web hosting provider to run _chown -R www /path/to/your/gallery/_ since maybe your web hosting provider accidentally changed the owner when restoring a backup or because they thought that was the right thing to do.

### How can I fix the filesystem permissions of the Gallery storage folder?

Usually you get either an ERROR_PLATFORM_FAILURE or the upgrade wizard reports that the filesystem permissions are wrong for your storage folder, i.e. it can't write to all files and subfolders in that folder anymore.

*   To fix the problem, you can try the _Fix the storage folder_ (make it writeable) tool in _http://www.example.com/gallery2/lib/support/_ on the _Filesystem Permissions_ page.
*   You can also try to change the permissions to _777_ (read/write for everyone) with your FTP program. If all fails, please ask in the forum for help such that we can verify that it is indeed a filesystem permissions problem. If it actually is one, you'll have to ask your web hosting provider to change the filesystem permissions _recursively_ for you (e.g. chmod -R 777 g2data).
*   What might help is removing all cached data from the storage folder. Please see Gallery 2 FAQ: [How can I clear cached data?](http://codex.galleryproject.org/G2:FAQ#How_can_I_clear_cached_data.3F)
*   A common issue is:

    <pre>Error (ERROR_PLATFORM_FAILURE) :
    in modules/core/classes/GalleryTemplate.class at line 123 (gallerycoreapi::error)
    in modules/core/classes/GalleryTemplate.class at line 456 (gallerytemplate::_initcompiledtemplatedir)
    				</pre>

    In such cases, if the above suggestions do not help, delete _g2data/smarty/templates_c/_ folder via FTP or whatever tool you use to upload / manage files on your website.
*   If the problem is not resolved or reappears after a while, ask your web hosting provider whether there are any scripts that change the owner of the files to the account owner.
