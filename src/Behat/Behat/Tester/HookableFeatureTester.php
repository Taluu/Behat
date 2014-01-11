<?php

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Behat\Tester;

use Behat\Behat\Tester\Event\FeatureTested;
use Behat\Behat\Tester\Result\FeatureTestResult;
use Behat\Behat\Tester\Result\HookedFeatureTestResult;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Testwork\Call\CallResults;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Suite\Suite;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatching feature tester.
 *
 * Feature tester dispatching BEFORE/AFTER hooks and events during tests.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class HookableFeatureTester extends FeatureTester
{
    /**
     * @var HookDispatcher
     */
    private $hookDispatcher;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Sets hook dispatcher.
     *
     * @param HookDispatcher $hookDispatcher
     */
    public function setHookDispatcher(HookDispatcher $hookDispatcher)
    {
        $this->hookDispatcher = $hookDispatcher;
    }

    /**
     * Sets event dispatcher.
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    protected function testFeature(Suite $suite, Environment $environment, FeatureNode $feature, $skip = false)
    {
        $beforeHooks = (!$skip && $this->hookDispatcher)
            ? $this->dispatchBeforeHooks($suite, $environment, $feature)
            : new CallResults();
        $this->eventDispatcher && $this->dispatchBeforeEvent($suite, $environment, $feature, $beforeHooks);

        $skip = $skip || $beforeHooks->hasExceptions();
        $result = parent::testFeature($suite, $environment, $feature, $skip);

        $afterHooks = (!$skip && $this->hookDispatcher)
            ? $this->dispatchAfterHooks($suite, $environment, $feature, $result)
            : new CallResults();
        $result = new HookedFeatureTestResult(
            $result->getScenarioTestResults(),
            CallResults::merge($beforeHooks, $afterHooks)
        );
        $this->eventDispatcher && $this->dispatchAfterEvent($suite, $environment, $feature, $result, $afterHooks);

        return $result;
    }

    /**
     * Dispatches BEFORE event.
     *
     * @param Suite       $suite
     * @param Environment $environment
     * @param FeatureNode $feature
     * @param CallResults $hookCallResults
     */
    private function dispatchBeforeEvent(
        Suite $suite,
        Environment $environment,
        FeatureNode $feature,
        CallResults $hookCallResults
    ) {
        $event = new FeatureTested($suite, $environment, $feature, null, $hookCallResults);

        $this->eventDispatcher->dispatch(FeatureTested::BEFORE, $event);
    }

    /**
     * Dispatches BEFORE event hooks.
     *
     * @param Suite       $suite
     * @param Environment $environment
     * @param FeatureNode $feature
     *
     * @return CallResults
     */
    private function dispatchBeforeHooks(Suite $suite, Environment $environment, FeatureNode $feature)
    {
        $event = new FeatureTested($suite, $environment, $feature);

        return $this->hookDispatcher->dispatchEventHooks(FeatureTested::BEFORE, $event);
    }

    /**
     * Dispatches AFTER event hooks.
     *
     * @param Suite             $suite
     * @param Environment       $environment
     * @param FeatureNode       $feature
     * @param FeatureTestResult $result
     *
     * @return CallResults
     */
    private function dispatchAfterHooks(
        Suite $suite,
        Environment $environment,
        FeatureNode $feature,
        FeatureTestResult $result
    ) {
        $event = new FeatureTested($suite, $environment, $feature, $result);

        return $this->hookDispatcher->dispatchEventHooks(FeatureTested::AFTER, $event);
    }

    /**
     * Dispatches AFTER event.
     *
     * @param Suite             $suite
     * @param Environment       $environment
     * @param FeatureNode       $feature
     * @param FeatureTestResult $result
     * @param CallResults       $hookCallResults
     */
    private function dispatchAfterEvent(
        Suite $suite,
        Environment $environment,
        FeatureNode $feature,
        FeatureTestResult $result,
        CallResults $hookCallResults
    ) {
        $event = new FeatureTested($suite, $environment, $feature, $result, $hookCallResults);

        $this->eventDispatcher->dispatch(FeatureTested::AFTER, $event);
    }
}
