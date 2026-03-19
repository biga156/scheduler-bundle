# EasyAdmin Integration

Optional integration with EasyAdmin for embedding the scheduler in your admin panel.

## Overview

The Caeligo Scheduler Bundle ships its own standalone dashboard. No EasyAdmin dependency is required. However, if your project uses EasyAdmin, you can link to the scheduler dashboard from your admin menu.

## Adding a Menu Item

In your EasyAdmin dashboard controller, add a menu item that links to the scheduler:

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

class DashboardController extends AbstractDashboardController
{
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        
        // ... your other menu items ...
        
        yield MenuItem::linkToUrl(
            'Scheduler',
            'fa fa-clock',
            $this->generateUrl('caeligo_scheduler_index')
        );
    }
}
```

This opens the scheduler's standalone dashboard when clicked.

## Styling Consistency

The scheduler dashboard uses Bootstrap 5, which may or may not match your EasyAdmin theme. If you need deeper visual integration, you can override the bundle's templates:

### Overriding Templates

Create your own templates in your project that extend the bundle's templates:

```
templates/bundles/CaeligoSchedulerBundle/
├── base.html.twig
├── dashboard/
│   ├── index.html.twig
│   ├── task_edit.html.twig
│   ├── task_logs.html.twig
│   └── settings.html.twig
└── _partials/
    └── ...
```

Symfony's template override mechanism will automatically use your templates instead of the bundle's defaults.

## Future Plans

A deeper EasyAdmin integration (CRUD-based) may be provided in a future version. For now, the standalone dashboard gives full functionality without any EasyAdmin dependency.
