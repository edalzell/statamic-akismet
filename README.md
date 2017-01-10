# Installation

1. Download and unzip
2. Copy `Akismet` folder to `yoursite/site/addons`

# Configuration 
1. Visit `yoursite.com/cp/addons/akismet/settings` or `CP > Configure > Addons > Akismet`
2. Add your Akismet key (get it [here](https://akismet.com/account/)
3. Set which form you'd like to guard against spam
4. Set the fields that map to `author`, `email` & `content`. All of these fields are checked for spam.

# Usage

In your form, check to see if the submission was spam, and display the normal success message:
```
{{ if errors && !error:is_spam }}
	<div class="alert alert-danger">
		{{ errors }}
			{{ value }}<br>
		{{ /errors }}
	</div>
{{ /if }}

{{ if error:is_spam || success }}
	<div class="alert alert-success">
		Form was submitted successfully.
	</div>
{{ /if }}
```

Anything deemed spam by Akismet is placed in the Spam Queue, available at `yoursite.com]/cp/addons/akismet`:
![Spam Queue  Statamic 2017-01-02 17-14-43.png](https://bitbucket.org/repo/reMMgA/images/2526904260-Spam%20Queue%20%20Statamic%202017-01-02%2017-14-43.png)

From there you can approve it, i.e. it's not spam and complete the form submission, or discard it and delete it from the queue.