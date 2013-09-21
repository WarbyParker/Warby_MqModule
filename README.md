# MQ Module

**Author**: Warby Parker (Jarad Delorenzo, Jeff Uthaichai, Jesse Zlata, Karina Ruzinov)

**Version**: 0.1.1

## Description

This Magento module adds the ability to post messages to RabbitMQ.

This version (0.1.1) can only post messages in response to sales order confirmation. If a message cannot be published to the queue, it will be flagged. [By default, the module sets up a Magento cron job that retries flagged orders every 5 minutes](https://github.com/WarbyParker/Warby_MqModule/blob/master/code/etc/config.xml#L51).

## Dependencies

- [modman](https://github.com/colinmollenhour/modman) for module installation.
- PHP with [BC Math enabled](http://www.php.net/manual/en/bc.installation.php).

Additionally, this module bundles [PHPAmqpLib](https://github.com/videlalvaro/php-amqplib).

## Installation

    $ cd /var/www
    $ modman init
    $ modman clone git@github.com:WarbyParker/Warby_MqModule.git

## Configuration

### Credentials

In the Magento Admin, navigate to:

- System > Configuration > Advanced > Mq Module

![Mai Configs](http://i.imgur.com/Pi95aeI.png)

## Licensing

[MIT](http://opensource.org/licenses/MIT)
