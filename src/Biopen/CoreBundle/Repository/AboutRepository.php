<?php

namespace Biopen\CoreBundle\Repository;

use Doctrine\ODM\MongoDB\DocumentRepository;
/**
 * AboutRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AboutRepository extends DocumentRepository
{
	public function findAllOrderedByPosition()
	{
	  return $this->createQueryBuilder()
	      ->sort('position', 'ASC')
	      ->getQuery()
	      ->execute();
	}
}
