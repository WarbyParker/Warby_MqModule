# MQ Module

**Author**: Warby Parker (Jarad Delorenzo, Jeff Uthaichai, Jesse Zlata, Karina Ruzinov)

**Version**: 0.1.1

## Description

This Module was built to enable Magento to post messages to RabbitMQ. Currently it will post a message on sales order confirmation.
If an order can not be sent to the queue it will be flaged and retried with a Magento cron job.
There is also a component to read from a queue but it is not implememted yet.

## Dependencies 

- [modman](https://github.com/colinmollenhour/modman) for module installation.
- PHP should be configured with BC Math enabled

## Installation

    $ cd /var/www                                         
    $ modman init                                         
    $ modman clone git@github.com:WarbyParker/Warby_MqModule.git

## Configuration

![Mai Configs](http://i.imgur.com/Pi95aeI.png)

## Licensing/Legal

[MIT](http://opensource.org/licenses/MIT)
