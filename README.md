# captchaeu

## Captcha.eu for TYPO3 powermail to prevent spam

This extensions implements captcha.eu



## Installation

- Just install this extension - e.g. `composer require captcha-eu/typo3-powermail`
- Register your domain to www.captcha.eu
- Add publickey and restkey to TypoScript Constants (see example below)
- add a new field "captcha.eu" somewhere in your form (position doesnt matter)
- Have fun

Example for TypoScript Constants:

```
plugin.tx_captchaeu.publickey = 6LdsBBUTAAAAAKMhI67inzeAvzBh5JdRRxlCwbTz
plugin.tx_captchaeu.restkey = 6LdsBBUTAAAAAKMhaaaainzeAvzBh5JdRRxlCwbyy
```

## Notes and best practice

Be sure to have spamshield enabled in powermail (TypoScript setup):


```
plugin.tx_powermail.settings.setup.spamshield._enable = 1
```

Keep up to date if powermail recognize spam (TypoScript setup):

```
# Get an email if spam was recognized
plugin.tx_powermail.settings.setup.spamshield.email = spamreceiver@yourdomain.org

# Write to a logfile when spam was recognized
plugin.tx_powermail.settings.setup.spamshield.logfileLocation = typo3temp/logs/powermailSpam.log
```


## Compatibility

- TYPO3: 10.4 LTS, 11.5 LTS, 12.4 LTS, 13.4 LTS
- powermail: 8.5 - 13.x

## Changelog

| Version | Date       | Description                                                           |
|---------|------------|-----------------------------------------------------------------------|
| 1.1.0   | 2026-04-28 | Add TYPO3 v13 support and broaden compatibility to TYPO3 10-13 / powermail 8.5-13 |
| 1.0.0   | 2023-08-30 | Initial upload - have fun                                             |
