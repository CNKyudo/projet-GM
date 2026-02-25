<?php

namespace App\Listener;

use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Tool\ActorProviderInterface;

/**
 * @phpstan-ignore missingType.generics
 */
class LoggableListener extends \Gedmo\Loggable\LoggableListener
{
    public function setUsername($username)
    {
        if (is_string($username)) {
            $this->username = $username;
        } elseif (is_object($username) && method_exists($username, 'getId')) {
            $this->username = (string) $username->getId();
        } elseif (is_object($username) && method_exists($username, 'getUserIdentifier')) {
            $this->username = (string) $username->getUserIdentifier();
        } elseif (is_object($username) && method_exists($username, 'getUsername')) {
            $this->username = (string) $username->getUsername();
        } elseif (is_object($username) && method_exists($username, '__toString')) {
            $this->username = $username->__toString();
        } else {
            throw new InvalidArgumentException('Username must be a string, or object should have method getId, getUserIdentifier, getUsername or __toString');
        }
    }

    protected function getUsername(): ?string
    {
        if ($this->actorProvider instanceof ActorProviderInterface) {
            $actor = $this->actorProvider->getActor();

            if (is_string($actor) || null === $actor) {
                return $actor;
            }

            if (method_exists($actor, 'getId')) {
                return (string) $actor->getId();
            }

            if (method_exists($actor, 'getUserIdentifier')) {
                return (string) $actor->getUserIdentifier();
            }

            if (method_exists($actor, 'getUsername')) {
                return (string) $actor->getUsername();
            }

            if (method_exists($actor, '__toString')) {
                return $actor->__toString();
            }

            throw new UnexpectedValueException(\sprintf('The loggable extension requires the actor provider to return a string or an object implementing the "getId()", "getUserIdentifier()", "getUsername()", or "__toString()" methods. "%s" cannot be used as an actor.', get_class($actor)));
        }

        return $this->username;
    }
}
