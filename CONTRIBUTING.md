# Contributing

First of all, **thank you** for contributing, **you are awesome**!

Everybody should be able to help. Here's how you can do it

- [Fork it](https://github.com/darkwood-com/flow/fork)
- Improve it
- Submit a [pull request](https://help.github.com/articles/creating-a-pull-request)

Here's some tips to make you the best contributor ever

- [Bug reports](#bug-reports)
- [Feature requests](#feature-requests)
- [Creating a pull request](#creating-a-pull-request)
- [Coding standard](#coding-standard)

## Bug reports

Please search existing issues first to make sure this is not a duplicate.
Every issue report has a cost for the developers required to field it; be
respectful of others' time and ensure your report isn't spurious prior to
submission. Try to be as detailed as possible in your problem description
to help us fix the bug. Please adhere to
[sound bug reporting principles](http://www.chiark.greenend.org.uk/~sgtatham/bugs.html).

## Feature requests

If you wish to propose a feature, please submit an issue. Try to explain your
use case as fully as possible to help us understand why you think the feature
should be added.

## Creating a pull request

First [fork the repository](https://help.github.com/articles/fork-a-repo/) on
GitHub.

Then clone your fork:

```bash
git clone git@github.com:darkwood-com/flow.git
git checkout -b bug-or-feature-description
```

And install the dependencies:

```bash
composer install
```

Write your code and add tests. Then run the tests:

```bash
make test
```

Commit your changes and push them to GitHub:

```bash
git commit -m ":sparkles: Introduce awesome new feature"
git push -u origin bug-or-feature-description
```

Then [create a pull request](https://help.github.com/articles/creating-a-pull-request/)
on GitHub.

If you need to make some changes, commit and push them as you like. When asked
to squash your commits, do so as follows:

```bash
git rebase -i
git push origin bug-or-feature-description -f
```

## Coding standard

This project follows the [Symfony](https://symfony.com/doc/current/contributing/code/standards.html) coding style.
Please make sure your pull requests adhere to this standard.

To fix, execute this command after [download PHP CS Fixer](https://cs.symfony.com/):

```bash
make php-cs-fixer
```
