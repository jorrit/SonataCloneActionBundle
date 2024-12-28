<?php

namespace Jorrit\SonataCloneActionBundle\Controller;

use Jorrit\SonataCloneActionBundle\Admin\Extension\CloneAdminExtension;
use RuntimeException;
use Sonata\AdminBundle\Admin\Pool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CloneController extends AbstractController
{
    private Pool $pool;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function __invoke(Request $request): Response
    {
        if (!$request->attributes->has('_sonata_admin')) {
            throw $this->createNotFoundException('Route should have _sonata_admin attribute');
        }

        try {
            $admin = $this->pool->getAdminByAdminCode($request->attributes->get('_sonata_admin'));
        } catch (ServiceNotFoundException $e) {
            throw new RuntimeException('Unable to find the Admin instance', $e->getCode(), $e);
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

        $routeParameters = [
            '_sonata_admin' => $request->attributes->get('_sonata_admin'),
            CloneAdminExtension::REQUEST_ATTRIBUTE => $subject,
        ];

        // Copy ids of parents.
        for ($currentAdmin = $admin; $currentAdmin->isChild(); ) {
            $currentAdmin = $currentAdmin->getParent();
            $idParameter = $currentAdmin->getIdParameter();
            $routeParameters[$idParameter] = $request->attributes->get($idParameter);
        }

        return $this->forward($controllerName.'::createAction', $routeParameters);
    }
}
