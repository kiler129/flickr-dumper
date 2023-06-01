<?php
declare(strict_types=1);

namespace App\Struct;

use App\Exception\InvalidArgumentException;
use App\Exception\UnderflowException;

/**
 * Implements a list of objects that can be borrowed and returned with order kept internally
 *
 * This class functions similarly to a SplMinHeap/SplMaxHeap but with arbitrary insertion order that is kept as
 * the private state of the library.
 *
 * @template TElement of object
 */
class OrderedObjectLibrary
{
    public const BORROW_TOP_FIRST    = -1;
    public const BORROW_BOTTOM_FIRST = +1;

    /** @var int Counter to know order of next new item, depending on the mode it will count up or down */
    private int $orderCounter = 0;

    /** @var \SplObjectStorage Permanent memory of order of borrowed items */
    private \SplObjectStorage $order;

    /** @var \SplPriorityQueue Ordered list of items available to be borrowed */
    private \SplPriorityQueue $available;

    /** @var callable(): TElement Factory used to create new items to borrow when the $available is empty */
    private mixed $createNewElement;

    /**
     * @param int $borrowOrder                             Defines if items are borrowed from the top-to-bottom or
     *                                                     bottom-to-top
     * @param callable(): TElement|null $newElementFactory Creates new elements when attempting to borrow from empty
     *                                                     library. If not specified only the $initialState elements
     *                                                     will be available; if the library runs out and no
     *                                                     $newElementFactory was specified an exception will be thrown.
     * @param iterable<TElement>|null $initialState        List of elements to add to the library initially. If not
     *                                                     specified new elements will be created using
     *                                                     $newElementFactory on-demand.
     */
    public function __construct(
        private int $borrowOrder = self::BORROW_TOP_FIRST,
        ?callable $newElementFactory = null,
        ?iterable $initialState = null
    ) {
        $this->order = new \SplObjectStorage();
        $this->available = new \SplPriorityQueue();
        $this->createNewElement = $newElementFactory ?? [$this, 'throwLibraryEmpty'];

        if ($initialState !== null) {
            foreach ($initialState as $element) {
                $this->addNewElement($element);
            }
        }
    }

    /**
     * Borrows an element from the library. Once borrowed the callee is in the full control of the reference to it.
     *
     * @return TElement
     */
    public function borrow(): mixed
    {
        if ($this->available->isEmpty()) {
            $element = ($this->createNewElement)();
            $this->addNewElement($element);
        }

        return $this->available->extract();
    }

    /**
     * Return borrowed element. Once returned the callee is prohibited from using it or holding a reference to it.
     *
     * @param TElement $element
     */
    public function return(mixed $element): void
    {
        if (!$this->order->contains($element)) {
            throw new InvalidArgumentException(
                \sprintf(
                    'Element %s was never borrowed from this instance of %s',
                    is_object($element) ? $element::class : \gettype($element),
                    self::class
                )
            );
        }

        $this->available->insert($element, $this->order[$element]);
    }

    /**
     * @param TElement $element
     */
    private function addNewElement(mixed $element): void
    {
        $this->available->insert($element, $this->orderCounter);
        $this->order->attach($element, $this->orderCounter);

        $this->orderCounter += $this->borrowOrder;
    }

    private function throwLibraryEmpty(): mixed
    {
        throw new UnderflowException(
            \sprintf('The %s is empty and no method to create new elements was defined', self::class)
        );
    }
}
