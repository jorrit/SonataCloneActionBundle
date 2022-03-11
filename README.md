# Sonata Admin Clone Action

[![Latest Stable Version](http://poser.pugx.org/jorrit/sonata-clone-action-bundle/v)](https://packagist.org/packages/jorrit/sonata-clone-action-bundle) [![Total Downloads](http://poser.pugx.org/jorrit/sonata-clone-action-bundle/downloads)](https://packagist.org/packages/jorrit/sonata-clone-action-bundle) [![Latest Unstable Version](http://poser.pugx.org/jorrit/sonata-clone-action-bundle/v/unstable)](https://packagist.org/packages/jorrit/sonata-clone-action-bundle) [![License](http://poser.pugx.org/jorrit/sonata-clone-action-bundle/license)](https://packagist.org/packages/jorrit/sonata-clone-action-bundle) [![PHP Version Require](http://poser.pugx.org/jorrit/sonata-clone-action-bundle/require/php)](https://packagist.org/packages/jorrit/sonata-clone-action-bundle)

Adds a clone action to Sonata Admin. This allows you to add a clone button to
your list action that leads to a create form with the values of the cloned item
prefilled.

The clone action does not create the clone in the database, this happens only
when the create form is submitted.

## Installation

```bash
$ composer require jorrit/sonata-clone-action-bundle
```

## Setup

The extension is registered just like any other Sonata Admin extension.
For more information regarding extensions, see the [Sonata Admin documentation](https://sonata-project.org/bundles/admin/3-x/doc/reference/extensions.html).

### Add to specific admins

Add the following code to services.yml to add the extension to one or more admin classes.

Replace `admin1` and `admin2` with the service names of your admin classes.

```yaml
    admin.clone.extension:
        class: Jorrit\SonataCloneActionBundle\Admin\Extension\CloneAdminExtension
        tags:
            - { name: sonata.admin.extension, target: admin1 }
            - { name: sonata.admin.extension, target: admin2 }
```

### Add to all admins

Add the following code to services.yml to add the extension all admin classes.

```yaml
    admin.clone.extension:
        class: Jorrit\SonataCloneActionBundle\Admin\Extension\CloneAdminExtension
        tags:
            - { name: sonata.admin.extension, global: true }
```

### Add the action to your admin list

Edit your admin class to add `clone` to the list of actions:

```php
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ...
            ->add('_action', null, [
                'actions' => [
                    'edit' => [],
                    'delete' => [],
                    'clone' => [],
                ]
            ]);
    }
```
