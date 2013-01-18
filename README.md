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
   grant all on base.* to user@localhost identified by "password"
5.