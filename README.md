AWS collector

Install:

1. You must install aws-sdk
   Use this instruction:
   https://github.com/aws/aws-sdk-php#installing-via-composer

2. Change path for aws-sdk in file bypassing.php
   default path is "require 'vendor/autoload.php';"

3. Copy file base.conf.example to file base.conf and change 
   user, password and host for mysql database.

4. Create user with privalaging for databse 'r2'. See command below:
   'grant all on base.* to user@localhost identified by "password"'

Run command for refreshing information about user's files in S3

1. Run in console command bypassing.php with paramter user_id
   Example:
 php bypassing.php user@company.com

Where  user@company.com is username in database
 

Show information on web.

1. You need copy files bs3.php index.html and file base.conf and directories jquery and css to web directory 

2. Run browser for view result  

Information:

Database: r2

Table s3objects
`s3objects (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_parent` bigint(20) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `folder` tinyint(1) DEFAULT NULL,
  `size` bigint(20) DEFAULT NULL,
  `timepassing` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `actual` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
)
