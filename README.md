# renegade334/phergie-irc-plugin-react-ping

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin for verifying that the client connection has not been dropped by sending self CTCP PINGs after periods of inactivity.

If the ping is not received within a given timeout period, the plugin tells the client to disconnect the connection.

[![Build Status](https://secure.travis-ci.org/Renegade334/phergie-irc-plugin-react-ping.png?branch=master)](http://travis-ci.org/Renegade334/phergie-irc-plugin-react-ping)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "renegade334/phergie-irc-plugin-react-ping": "~2"
    }
}
```

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Configuration

```php
return [
    'plugins' => [
        // configuration
        new \Renegade334\Phergie\Plugin\React\Ping\Plugin([
            // Optional: the number of seconds of inactivity before a PING is sent (default: 90)
            'wait' => 90,

            // Optional: the number of seconds to wait for a PING response before disconnecting (default: 20)
            'timeout' => 20,
        ])
    ]
];
```

The plugin is written such that it honours being filtered by a [ConnectionFilter](https://github.com/phergie/phergie-irc-plugin-react-eventfilter#connectionfilter) if desired.

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.
