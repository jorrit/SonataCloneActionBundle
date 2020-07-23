<?php

namespace Jorrit\SonataCloneActionBundle\Controller;

use Jorrit\SonataCloneActionBundle\Admin\Extension\CloneAdminExtension;
use Sonata\AdminBundle\Admin\Pool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;

class CloneController extends AbstractController
{
    /**
     * @var Pool
     */
    private $pool;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function __invoke(Request $request)
    {
        if (!$request->attributes->has('_sonata_admin')) {
            return $this->createNotFoundException('Route should have _sonata_admin attribute');
        }

        try {
            $admin = $this->pool->getAdminByAdminCode($request->attributes->get('_sonata_admin'));
        } catch (ServiceNotFoundException $e) {
            throw new \RuntimeException('Unable to find the Admin instance', $e->getCode(), $e);
        }

        // Check if the user has clone permission.
        $admin->checkAccess('clone');

        // Fetch the original item and check SHOW access.
        $id = $request->get($admin->getIdParameter());
        $subject = $admin->getObject($id);
        if (!$subject) {
            throw $this->createNotFoundException(sprintf('unable to find the object with id: %s', $id));
        }

        $admin->checkAccess('show', $subject);

        $controllerName = $admin->getBaseControllerName();

        return $this->forward(
            $controllerName.'::createAction',
            [
                '_sonata_admin' => $request->attributes->get('_sonata_admin'),
                CloneAdminExtension::REQUEST_ATTRIBUTE => $subject,
            ]
        );
    }
}
