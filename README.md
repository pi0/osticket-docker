[osTicket](http://osticket.com) Docker Image
=============================================
[![Docker Pulls](https://img.shields.io/docker/pulls/osticket/osticket.svg)](https://hub.docker.com/r/osticket/osticket)
  
<a href="http://osticket.com"><img src="http://osticket.com/sites/default/files/osTicket.jpg"
align="left" hspace="10" vspace="6"></a>

Docker image for running always fresh and bleeding edge version of [osTicket](http://osticket.com/) Shipping with plugins and language packs. **osTicket** is a widely-used open source support ticket system. It seamlessly
integrates inquiries created via email, phone and web-based forms into a
simple easy-to-use multi-user web interface. Manage, organize and archive
all your support requests and responses in one place while providing your
customers with accountability and responsiveness they deserve.

## How osTicket works for you
  1. Users create tickets via your website, email, or phone
  2. Incoming tickets are saved and assigned to agents
  3. Agents help your users resolve their issues

osTicket is an attractive alternative to higher-cost and complex customer
support systems; simple, lightweight, reliable, open source, web-based and
easy to setup and use. The best part is, it's completely free.

  * Based on [PaaS](https://github.com/pi0/paas) Image.
  * Works with a linked Mariadb docker container.
  * Automates configuration file & database installation
  * Email support 
  * Always have bleeding edge version 
  * Stock plugins are always installed and updated

# Quick Start

Ensure you have a MySQL container running that osTicket can use to store its data.

```bash
docker run --name osticket_mysql -d -e MYSQL_ROOT_PASSWORD=secret \
 -e MYSQL_USER=osticket -e MYSQL_PASSWORD=secret -e MYSQL_DATABASE=osticket mariadb
```

**Now run this image and link the MySQL container.**

```bash
docker run --name osticket -d --link osticket_mysql:mysql -p 8080:80 osticket/osticket
```

Wait for the installation to complete then browse to your osTicket staff control panel at `http://localhost:8080/scp`. Login with default admin user & password:

* username: **ostadmin**
* password: **Admin1**

Now configure as required. If you are intending on using this image in production, please make sure you change the
passwords above and read the rest of this documentation!

# MySQL connection

The recommended connection method is to link your MySQL container to this image with the alias name ```mysql```. However, if you
are using an external MySQL server then you can specify the connection details using environmental variables.

osTicket requires that the MySQL connection specifies a user with full permissions to the specified database. This is required for the automatic
 database installation.

The osTicket configuration file is re-created from the template every time the container is started. This ensures the
MySQL connection details are always kept up to date automatically in case of any changes.

## Linked container Settings

There are no mandatory settings required when you link your MySQL container with the alias `mysql` as per the quick start example.

## External MySQL connection settings

The following environmental variables should be set when connecting to an external MySQL server.

`MYSQL_HOST`

The host name or IP address of the MySQL host to connect to. This is not required when you link a container
with the alias `mysql`. This must be provided if not using a linked container.

`MYSQL_PASSWORD`

The password for the specified user used when connecting to the MySQL server. By default will use the environmental variable
`MYSQL_PASSWORD` from the linked MySQL container if this is not explicitly specified. This must be provided if not
using a linked container.

`MYSQL_PREFIX`

The table prefix for this installation. Unlikely you will need to change this as customisable table prefixes are
designed for shared hosting with only a single MySQL database available. Defaults to 'ost_'.

`MYSQL_DATABASE`

The name of the database to connect to. Defaults to 'osticket'.

`MYSQL_USER`

The user name to use when connecting to the MySQL server. Defaults to 'osticket'.

# Mail Configuration

The image does not run a MTA. Although one could be installed quite easily, getting the setup so that external mail servers
will accept mail from your host & domain is not trivial due to anti-spam measures. This is additionally difficult to do 
from ephemeral docker containers that run in a cloud where the host may change etc.

Hence this image supports osTicket sending of mail by sending directly to designated a SMTP server. 
However, you must provide the relevant SMTP settings through environmental variables before this will function.

To automatically collect email from an external IMAP or POP3 account, configure the settings for the relevant email address in 
your admin control panel as normal (Admin Panel -> Emails). 

## SMTP Settings

`SMTP_HOST`

The host name (or IP address) of the SMTP server to send all outgoing mail through. Defaults to 'localhost'.

`SMTP_PORT`

The TCP port to connect to on the server. Defaults to '25'. Usually one of 25, 465 or 587.

`SMTP_FROM`

The envelope from address to use when sending email (note that is not the same as the From: header). This must be 
provided for sending mail to function. However, if not specified, this will default to the value of `SMTP_USER` if this is provided.

`SMTP_TLS`

Boolean (1 or 0) value indicating if TLS should be used to create a secure connection to the server. Defaults to true.

`SMTP_TLS_CERTS`

If TLS is in use, indicates file containing root certificates used to verify server certificate. Defaults to system
installed ca certificates list. This would normally only need changed if you are using your own certificate authority
or are connecting to a server with a self signed certificate.

`SMTP_USER`

The user identity to use for SMTP authentication. Specifying a value here will enable SMTP authentication. This will also
be used for the `SMTP_FROM` value if this is not explicitly specified. Defaults to no value.

`SMTP_PASSWORD`

The password associated with the user for SMTP authentication. Defaults to no value.

## IMAP/POP3 Settings

`CRON_INTERVAL`

Specifies how often (in minutes) that osTicket cron script should be ran to check for incoming emails. Defaults to 5 
minutes. Set to 0 to disable running of cron script. Note that this works in conjuction with the email check interval
specified in the admin control panel, you need to specify both to the value you'd like!

# Environmental Variables

`HOST`

Sets the 'host' part of the osTicket URL in database, eg. for links in ticket mails.

`ROOT_PATH`

If you want to host osTicket under a subdirectory path of your domain you must set this path here, so osTicket can properly reference it's source files. Defaults to `/`.

`INSTALL_SECRET`

Secret string value for OST installation. A random value is generated on start-up and persisted within the container if this is not provided.

*If using in production you should specify this so that re-creating the container does not cause
your installation secret to be lost!*

`INSTALL_CONFIG`

If you require a configuration file for osTicket with custom content then you should create one and mount it in your
container as a volume. The placeholders for the MySQL connection must be retained as these will be populated automatically
when the container starts. Set this environmental variable to the fully qualified file name of your custom configuration.
If not specified, the default osTicket sample configuration file is used.

`INSTALL_EMAIL`

Helpdesk email account. This is placed in the configuration file as well as the DB during installation.
Defaults to 'helpdesk@example.com'

## Database Installation Only

The remaining environmental variables can be used as a convenience to provide defaults during the automated database
installation but most of these settings can be changed through the admin panel if required. These are only used when creating
the initial database.

`INSTALL_NAME`

The name of the helpdesk to create if installing. Defaults to "My Helpdesk".

`ADMIN_FIRSTNAME`

First name of automatically created administrative user. Defaults to 'Admin'.

`ADMIN_LASTNAME`

Last name of automatically created administrative user. Defaults to 'User'.

`ADMIN_EMAIL`

Email address of automatically created administrative user. Defaults to 'admin@example.com'.

`ADMIN_USERNAME`

User name to use for automatically created administrative user. Defaults to 'ostadmin'.

`ADMIN_PASSWORD`

Password to use for automatically created administrative user. Defaults to 'Admin1'.

# Modifications

This image was put together relatively quickly and could probably be improved to meet other use cases.

Please feel free to open an issue if you have any changes you would like to see. All pull requests are also appreciated!

# License

Installer script in this image and docs was based on [CampbellSoftwareSolutions/docker-osticket](https://github.com/CampbellSoftwareSolutions/docker-osticket) that itself was created from a fork of docker-osticket image by [Petter A. Helset](mailto:petter@helset.eu).
This image and source code is made available under the MIT licence. See the LICENSE file for details.
