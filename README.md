# skroutz
Export Magento products for skroutz.gr and bestprice.gr

This is a quick and dirty script to export Magento products suitable for the Greek price compare sites skroutz.gr and bestprice.gr. The same output is used for both sites.

Upload the files to your site, update the settings.ini.php file with your settings, and then use one of the following urls to display the output or store the output into a file on your server:


`https://your_site_address/FOLDER_OF_THE_SCRIPT/export.php`

This will generate a csv text output and display it in your browser. You can copy and paste it from your browser into a text editor, and open it with Excel/LibreOffice


`https://your_site_address/FOLDER_OF_THE_SCRIPT/export.php?format=skroutz`

This will generate an XML output and display it in your browser.


`https://your_site_address/FOLDER_OF_THE_SCRIPT/export.php?format=skroutzfile`

This will generate an XML output and store it in a file as specified in the OUTPUT_FILE parameter of the settings file. You can use the address of this file for skroutz.gr and bestprice.gr

