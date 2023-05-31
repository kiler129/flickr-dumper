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
            $this->validateFieldName($field, 'filtering', $classMeta);
            $scopedField = 'photo.' . $field;

            if ($value === null) {
                $qb->andWhere($qb->expr()->andX($qb->expr()->isNull($scopedField)));
                continue;
            }

            $param = ':' . \str_replace('.', '_', $field);
            if (\is_string($value) && \str_contains($value, '%')) {
                $expr = $qb->expr()->andX($qb->expr()->like($scopedField, $param));
            } elseif (\is_scalar($value) && \str_starts_with((string)$value, '!') && isset($value[1])) {
                $expr = $qb->expr()->andX($qb->expr()->neq($scopedField, $param));
                $value = \substr((string)$value, 1);
            } else {
                $expr = $qb->expr()->andX($qb->expr()->eq($scopedField, $param));
            }

            $qb->andWhere($expr)
               ->setParameter($param, $value);
        }

        $this->validateFieldName($sortField, 'sorting', $classMeta);
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

    private function validateFieldName(string $fieldName, string $context, ClassMetadata $classMetadata): void
    {
        if ($classMetadata->hasField($fieldName) || $classMetadata->hasAssociation($fieldName)) {
            return;
        }

        throw new InvalidArgumentException(
            \sprintf(
                'Field "%s" used for %s does not exist in entity "%s". Valid fields: %s',
                $fieldName,
                $context,
                $classMetadata->getName(),
                \implode(
                    ', ',
                    \array_merge(
                        \array_column($classMetadata->fieldMappings, 'fieldName'),
                        \array_column($classMetadata->associationMappings, 'fieldName'),
                    )
                )
            )
        );
    }
}
