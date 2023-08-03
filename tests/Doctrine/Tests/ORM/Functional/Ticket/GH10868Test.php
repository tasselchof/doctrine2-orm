<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

class GH10868Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEntitySchema([
            GH10868Shop::class,
            GH10868AcceptanceItem::class,
            GH10868Offer::class,
        ]);
    }

    public function testReferenceAndLazyLoadProxyAreTheSame(): void
    {
        $shop = new GH10868Shop();
        $offer = new GH10868Offer($shop, 1);
        $acceptanceItem = new GH10868AcceptanceItem();
        $acceptanceItem->offer = $offer;

        $this->_em->persist($shop);
        $this->_em->persist($offer);
        $this->_em->persist($acceptanceItem);
        $this->_em->flush();
        $this->_em->clear();

        $reference = $this->_em->getReference(GH10868Offer::class, [
            'shop' => $shop->id,
            'id' => $offer->id,
        ]);

        $reference->setName('Test 2');

        /** @var GH10868AcceptanceItem $item */
        $item = $this->_em->createQueryBuilder()
            ->select('ai')
            ->from(GH10868AcceptanceItem::class, 'ai')
            ->where('ai.id = :item')
            ->setParameter('item', $acceptanceItem->id)
            ->getQuery()->getOneOrNullResult();

        $acceptanceItemReloaded = $this->_em->find(
            GH10868AcceptanceItem::class, $acceptanceItem->id
        );

        $acceptanceItemReloaded->sku = 'test';
        $item->getProductOffer()->setName('321');

        $this->_em->flush();

        self::assertSame($reference, $acceptanceItemReloaded->offer);
    }
}

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class GH10868AcceptanceItem
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var ?int
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity="GH10868Shop")
     * @ORM\JoinColumn(name="shop_id", referencedColumnName="id", nullable=false)
     */
    protected $shop;

    /**
     * @ORM\OneToOne(targetEntity="GH10868Offer", fetch="EXTRA_LAZY")
     * @ORM\JoinColumns({
     *     @ORM\JoinColumn(name="shop_id", referencedColumnName="shop_id"),
     *     @ORM\JoinColumn(name="productoffer_id", referencedColumnName="id", nullable=false)
     * })
     *
     * @var GH10868Offer
     */
    public $offer;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public ?string $sku = null;

    public function getProductOffer(): ?GH10868Offer
    {
        return $this->offer;
    }

    /**
     * @ORM\PreFlush
     */
    public function preFlush(PreFlushEventArgs $args)
    {
        $extendedValues = [];
        if (! empty($this->getProductOffer())) {
            $productOffer = $this->getProductOffer();

            $extendedValues[] = $productOffer->getName();
        }

        $extendedValues = array_filter($extendedValues);
    }
}

/**
 * @ORM\Entity
 */
class GH10868Offer
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="bigint")
     *
     * @var ?int
     */
    public $id;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\ManyToOne(targetEntity="GH10868Shop")
     * @ORM\JoinColumn(name="shop_id", referencedColumnName="id")
     */
    protected $shop;

    protected $name = 'Test';

    public function __construct(GH10868Shop $shop = null, int $id = null)
    {
        $this->shop = $shop;
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): GH10868Offer
    {
        $this->name = $name;

        return $this;
    }
}

/**
 * @ORM\Entity
 */
class GH10868Shop
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var ?int
     */
    public $id;
}