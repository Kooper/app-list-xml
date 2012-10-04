app-list-xml
============

Command-line utility for generating minimal APP-LIST.xml files in accordance with http://www.apsstandard.org/doc/package-format-specification-1.2/index.html#s.metalist


The utility adds APP-LIST.xml file to a specified ZIP archive. If ZIP archive already contains APP-LIST.xml file - the file will be replaced with actual one.

## Example

  php app-list-xml.php WordPress-3.4.2-3.app.zip

