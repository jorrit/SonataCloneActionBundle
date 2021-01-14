<?php

namespace Jorrit\SonataCloneActionBundle\Admin\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Jorrit\SonataCloneActionBundle\Controller\CloneController;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\TranslationBundle\Model\Gedmo\AbstractPersonalTranslatable;
use Sonata\TranslationBundle\Model\Gedmo\AbstractPersonalTranslation;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;

class CloneAdminExtension extends AbstractAdminExtension
{
    public const REQUEST_ATTRIBUTE = '_clone_subject';

    /**
     * @var PropertyListExtractorInterface
     */
    private $propertyInfoExtractor;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var ?TranslatableListener
     */
    private $translatableListener;

    public function __construct(
        PropertyListExtractorInterface $propertyInfoExtractor,
        EntityManagerInterface $entityManager,
        ?TranslatableListener $translatableListener = null
    ) {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->entityManager = $entityManager;
        $this->translatableListener = $translatableListener;
    }

    public function getAccessMapping(AdminInterface $admin)
    {
        return [
            'clone' => 'CREATE',
        ];
    }

    public function alterNewInstance(AdminInterface $admin, $object)
    {
        $request = $admin->getRequest();
        if ($request === null || !$request->attributes->has(self::REQUEST_ATTRIBUTE)) {
            return;
        }

        $subject = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        $subjectclass = get_class($subject);

        $idfields = $admin->getModelManager()->getIdentifierFieldNames($subjectclass);
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $properties = $this->propertyInfoExtractor->getProperties($subjectclass);

        foreach ($properties as $property) {
            // Skip identifier fields.
            if (\in_array($property, $idfields, true)) {
                continue;
            }

            // Skip unwritable fields.
            if (!$propertyAccessor->isWritable($object, $property)) {
                continue;
            }

            // Skip unreadable fields.
            if (!$propertyAccessor->isReadable($subject, $property)) {
                continue;
            }

            $propertyAccessor->setValue($object, $property, $propertyAccessor->getValue($subject, $property));
        }
    }

    /**
     * Store id of subject as hidden field so it can be read in prePersist().
     *
     * @param FormMapper $formMapper
     */
    public function configureFormFields(FormMapper $formMapper)
    {
        $admin = $formMapper->getAdmin();

        $request = $admin->getRequest();
        if ($request === null) {
            return;
        }

        // Read the subject id from the request attribute (on form display) or POST value (on submit).
        $subjectId = null;
        if ($request->attributes->has(self::REQUEST_ATTRIBUTE)) {
            $subject = $request->attributes->get(self::REQUEST_ATTRIBUTE);
            $subjectId = $admin->getModelManager()->getNormalizedIdentifier($subject);
        } else {
            $postValues = $request->request->get($admin->getUniqid());
            if ($postValues !== null && isset($postValues[self::REQUEST_ATTRIBUTE])) {
                $subjectId = $postValues[self::REQUEST_ATTRIBUTE];
            }
        }

        if ($subjectId !== null) {
            $formMapper->add(
                self::REQUEST_ATTRIBUTE,
                HiddenType::class,
                [
                    'data' => $subjectId,
                    'mapped' => false,
                ]
            );
        }
    }

    /**
     * Copy translations before persisting.
     *
     * @param AdminInterface $admin
     * @param object $object
     */
    public function prePersist(AdminInterface $admin, $object)
    {
        $request = $admin->getRequest();
        if ($request === null) {
            return;
        }

        $postValues = $request->request->get($admin->getUniqid());
        if ($postValues === null || !isset($postValues[self::REQUEST_ATTRIBUTE])) {
            return;
        }

        $subjectId = $postValues[self::REQUEST_ATTRIBUTE];
        $subject = $admin->getModelManager()->find($admin->getClass(), $subjectId);
        if (!$subject) {
            throw new \RuntimeException(sprintf('unable to find the object with id: %s', $subjectId));
        }

        if ($this->translatableListener !== null && $object instanceof AbstractPersonalTranslatable) {
            $eventAdapter = new \Gedmo\Translatable\Mapping\Event\Adapter\ORM();
            $config = $this->translatableListener->getConfiguration($this->entityManager, get_class($subject));
            $translationClass = $this->translatableListener->getTranslationClass($eventAdapter, $config['useObjectClass']);

            $translationRepository = $this->entityManager->getRepository($translationClass);
            $translations = $translationRepository->findBy(['object' => $subject]);
            foreach ($translations as $translation) {
                /* @var AbstractPersonalTranslation $clonedTranslation */
                $clonedTranslation = new $translationClass;
                $clonedTranslation->setLocale($translation->getLocale());
                $clonedTranslation->setContent($translation->getContent());
                $clonedTranslation->setField($translation->getField());
                $object->addTranslation($clonedTranslation);
                $this->entityManager->persist($clonedTranslation);
            }
        }
    }

    public function configureRoutes(AdminInterface $admin, RouteCollection $collection)
    {
        $collection->add(
            'clone',
            $admin->getRouterIdParameter().'/clone',
            [
                '_controller' => CloneController::class,
            ]
        );
    }

    public function configureListFields(ListMapper $listMapper)
    {
        $itemkeys = $listMapper->keys();

        foreach ($itemkeys as $itemkey) {
            $item = $listMapper->get($itemkey);
            if (($actions = $item->getOption('actions')) && isset($actions['clone'])) {
                $actions['clone']['template'] = '@SonataCloneAction/SonataAdmin/CRUD/list__action_clone.html.twig';
                $item->setOption('actions', $actions);
            }
        }
    }
}
