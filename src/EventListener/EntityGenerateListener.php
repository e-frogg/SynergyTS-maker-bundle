<?php

namespace Efrogg\SynergyMaker\EventListener;

use Efrogg\SynergyMaker\Event\EntityClassGeneratedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntityGenerateListener implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return [
            EntityClassGeneratedEvent::class => 'onEntityClassGenerated',
        ];
    }

    public function onEntityClassGenerated(EntityClassGeneratedEvent $event)
    {
//        if (is_a($event->getClassName(), ScheduledEventInterface::class, true)) {
//            $event->setExtends('ScheduledEventEntity');
//            $event->addImport('./Common/ScheduledEventEntity', null,['ScheduledEventEntity']);
//        }
//        if (is_a($event->getClassName(), SimulationAwareInterface::class, true)) {
//            $event->addImplements('SimulationEntityInterface');
//            $event->addImport('./Common/SimulationEntity', null, ['SimulationEntityInterface']);
//        }
    }
}
