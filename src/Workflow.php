<?php


namespace Alterway\Component\Workflow;


use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Workflow
{
    /**
     * @var Node
     */
    private $start;

    /**
     * @var NodeMap
     */
    private $nodes;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Node
     */
    private $current;


    public function __construct(Node $start, NodeMap $nodes, EventDispatcherInterface $eventDispatcher)
    {
        $this->start = $start;
        $this->nodes = $nodes;
        $this->eventDispatcher = $eventDispatcher;
        $this->current = null;
    }

    /**
     * Initializes the workflow with a given token.
     *
     * @param string $token
     *
     * @return Workflow
     *
     * @throws Exception\InvalidTokenException
     */
    public function initialize($token = null)
    {
        if (null === $token) {
            $this->current = $this->start;
        } elseif ($this->nodes->has($token)) {
            $this->current = $this->nodes->get($token);
        } else {
            throw new Exception\InvalidTokenException();
        }

        return $this;
    }

    /**
     * Moves the current token to the next node of the workflow.
     *
     * @param ContextInterface $context
     *
     * @return Workflow
     *
     * @throws Exception\NotInitializedWorkflowException
     * @throws Exception\NoOpenTransitionException
     * @throws Exception\MoreThanOneOpenTransitionException
     */
    public function next(ContextInterface $context)
    {
        if (null === $this->current) {
            throw new Exception\NotInitializedWorkflowException();
        }

        $oldNodeName = $this->current->getName();
        $transitions = $this->current->getOpenTransitions($context);

        if (0 === count($transitions)) {
            throw new Exception\NoOpenTransitionException();
        } elseif (1 < count($transitions)) {
            throw new Exception\MoreThanOneOpenTransitionException();
        }

        $transition = array_pop($transitions);
        $token = $transition->getDestination()->getName();

        $context->getTokenedObject()->setToken($token);

        // Pre event
        $this->eventDispatcher->dispatch($oldNodeName . '.departure', new Event($context, $oldNodeName));

        // Arrive
        $this->initialize($token);
        $this->eventDispatcher->dispatch($token . '.arrival', new Event($context, $oldNodeName, $token));

        // Post event
        // You could post persistence here, or sending email, or any other operations after arriving at a new node
//        $this->initialize($token);
        $this->eventDispatcher->dispatch($token . '.post_arrival', new Event($context, $token));

        return $this;
    }

    /**
     * Moves the current token to the next node of the workflow.
     *
     * @param ContextInterface $context
     *
     * @return Workflow
     *
     * @throws Exception\NotInitializedWorkflowException
     * @throws Exception\NoOpenTransitionException
     * @throws Exception\MoreThanOneOpenTransitionException
     */
    public function moveTo(ContextInterface $context, $destinationNode)
    {
        if (null === $this->current) {
            throw new Exception\NotInitializedWorkflowException();
        }

        $transitions = array_filter($this->current->getOpenTransitions($context), function (Transition $t) use ($destinationNode) {
            return $t->getDestination()->getName() === $destinationNode;
        });

        if (0 === count($transitions)) {
            throw new Exception\NoOpenTransitionException();
        } elseif (1 < count($transitions)) {
            throw new Exception\MoreThanOneOpenTransitionException();
        }

        $transition = array_pop($transitions);
        $token = $transition->getDestination()->getName();

        $this->initialize($token);
        $this->eventDispatcher->dispatch($token, new Event($context, $token));

        // Run this again until no open transitions

        return $this;
    }
}
