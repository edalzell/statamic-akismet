# Installation

1. Download and unzip
2. Copy `Akismet` folder to `yoursite/site/addons`

# Configuration 
1. Visit `yoursite.com/cp/addons/akismet/settings` or `CP > Configure > Addons > Akismet`
2. Add your Akismet key (get it [here](https://akismet.com/account/))
3. Set the roles that are allowed to access the spam queue
4. If you want to check user registrations, turn that toggle on and set the user fields
5. Set which form(s) you'd like to guard against spam
6. Set the fields that map to `author`, `email` & `content`. All of these fields are checked for spam.

# Testing

To test Akismet set the author to `viagra-test-123` or the email to `akismet-guaranteed-spam@example.com`. Populate all other required fields with typical values. The Akismet API will always return a true response to a valid request with one of those values. If you receive anything else, something is wrong in your client, data, or communications.

# Usage

If you're checking user registrations, make sure the user registration toggle is on. If the registration is spammy, an error will be returned.

For forms, use a normal Form submission. If it's spam, Statamic will ignore it and show a success message but Akismet will put it in the the Spam Queue, available at `yoursite.com]/cp/addons/akismet`:
![Spam Queue  Statamic 2017-01-02 17-14-43.png](https://bitbucket.org/repo/reMMgA/images/2526904260-Spam%20Queue%20%20Statamic%202017-01-02%2017-14-43.png)

From there you can approve it, i.e. it's not spam and complete the form submission, or discard it and delete it from the queue.

You can also mark submissions as spam, from the submission view.

**Widget**

You can put a widget on your Dashboard that shows the state of all of your spam queues. Add to your `Widgets` one of type `akismet`.
