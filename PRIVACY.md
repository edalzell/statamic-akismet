# Privacy Policy

## Addon Description

The Akismet addon, is a [Statamic](https://statamic.com) addon for Automattic's [Akismet](https://akismet.com) service.


## How It Works

1. The addon listens for 2 Statamic events, 'Form.submission.creating', 'user.registering', for when a form is submitted and a user registered, respectively.
2. Submits the information (see below for details) to the Akistmet service (https://akisment.com) to see if it is spam.
3. If spam
  * user information is silently discarded and no information is kept.
  * form submission is stored in Statamic's `storage` folder.
4. If not spam then it is stored as per Statamic's configuration. Please review Statamic's documentation for details on that.


## Data Storage & Access

As mentioned above, if the submission is spam, it is stored indefinitely in Statamic's `storage` directory.

It can be seen & removed via the Control Panel or removed manually as needed.


## Data Security

The addon is within the Statamic site and therefore all security is handled by Statamic.

Akismet Control Panel pages can be accessed by `super` users and any user given appropriate permission as per the addon's configuration.

Anyone who has access to Statamic's files on the webserver has access to the Akismet files.


## 3rd Parties

The addon only communicates with the Askimet service, and then only on the configured forms. If not set up, no information is stored by the addon.


