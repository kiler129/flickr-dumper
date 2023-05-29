<?php
declare(strict_types=1);

namespace App\Repository\Flickr;

use App\Entity\Flickr\Photo;
use App\Exception\InvalidArgumentException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

trait PhotoFilterAware
{
    protected function createFilteredPhotos(ClassMetadata $classMeta, QueryBuilder $qb, array $filters, $sortField, $sortDir): QueryBuilder
    {
        foreach ($filters as $field => $value) {
            if (!$classMeta->hasField($field)) {
                throw new InvalidArgumentException(
                    \sprintf(
                        'Field "%s" used for filtering does not exist in entity "%s"',
                        $field,
                        $classMeta->getName()
                    )
                );
            }


            if (\is_string($value) && \str_contains($value, '%')) {
                $expr = $qb->expr()->andX($qb->expr()->like('photo.' . $field, ':' . $field));
            } else {
                $expr = $qb->expr()->andX($qb->expr()->eq('photo.' . $field, ':' . $field));
            }

            //$qb->andWhere($expr);
            //$qb->setParameter(':' . $field, $value);
        }

        if (!$classMeta->hasField($sortField)) {
            throw new InvalidArgumentException(
                \sprintf(
                    'Field "%s" used for sorting does not exist in entity "%s"',
                    $sortField,
                    $classMeta->getName()
                )
            );
        }

        $order = \strtoupper($sortDir);
        if ($order !== 'ASC' && $order !== 'DESC') {
            throw new InvalidArgumentException(
                \sprintf(
                    'Invalid sort direction "%s" - it should be either ASC or DESC',
                    $sortDir,
                )
            );
        }

        $qb->orderBy('photo.' . $sortField, $order);

        return $qb;
    }
}
