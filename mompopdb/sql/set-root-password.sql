--
-- Sets the password for the root user in Mariadb
--
USE mysql;
UPDATE user SET password=PASSWORD("re:St@rt!9") WHERE user='root';
flush privileges;