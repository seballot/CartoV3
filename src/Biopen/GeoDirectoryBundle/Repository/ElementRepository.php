<?php

/**
 * This file is part of the GoGoCarto project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2018-04-01 15:09:37
 */
 

namespace Biopen\GeoDirectoryBundle\Repository;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Biopen\GeoDirectoryBundle\Document\ElementStatus;
use Biopen\GeoDirectoryBundle\Document\ModerationState;

/**
 * ElementRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ElementRepository extends DocumentRepository
{
  // public function findAll()
  // {
  //   $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
  //   return $qb->select('compactJson')->hydrate(false)->getQuery()->execute()->toArray(); 
  // }

  public function findDuplicatesAround($lat, $lng, $distance, $maxResults, $text)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $expr = $qb->expr()->operator('$text', array('$search' => $text));
    // convert kilometre in degrees
    $radius = $distance / 110;
    return $qb  //->limit($maxResults)
                ->equals($expr->getQuery())
                ->field('status')->gt(ElementStatus::Duplicate)
                ->field('geo')->withinCenter((float)$lat, (float)$lng, $radius)                
                ->sortMeta('score', 'textScore')
                ->hydrate(false)->getQuery()->execute()->toArray();    
  }

  public function findPerfectDuplicatesAround($lat, $lng, $distance, $maxResults, $text)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    // convert kilometre in degrees
    $radius = $distance / 110;
    return $qb  ->field('name')->equals($text)
                ->field('geo')->withinCenter((float)$lat, (float)$lng, $radius)           
                ->hydrate(false)->getQuery()->execute()->toArray();    
  }

  public function findWhithinBoxes($bounds, $request, $getFullRepresentation, $isAdmin = false, $limit = null)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $status = ($request->get('pendings') === "false") ? ElementStatus::AdminValidate : ElementStatus::PendingModification;
    $this->filterVisibles($qb, $status);

    $mainOptionId = $request->get('mainOptionId');
    if ($mainOptionId && $mainOptionId != "all") $qb->field('optionValues.optionId')->in(array((float) $optionId));

    // get elements within box
    foreach ($bounds as $key => $bound) 
      if (count($bound) == 4)        
        $qb->addOr($qb->expr()->field('geo')->withinBox((float) $bound[1], (float) $bound[0], (float) $bound[3], (float) $bound[2]));
    
    $this->selectJson($qb, $getFullRepresentation, $isAdmin);

    // execute request   
    $results = $this->queryToArray($qb); 

    return $results;
  }

  public function findElementsWithText($text)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $expr = $qb->expr()->operator('$text', array('$search' => (string) $text));
    
    $qb  //->limit(50)
                ->equals($expr->getQuery())        
                ->sortMeta('score', 'textScore');
    
    $this->filterVisibles($qb);
                
    return $this->queryToArray($qb);    
  }

  public function findPendings($getCount = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $qb->field('status')->in(array(ElementStatus::PendingAdd,ElementStatus::PendingModification));
    if ($getCount) $qb->count();
    
    return $qb->getQuery()->execute();
  }

  public function findModerationNeeded($getCount = false, $moderationState = null)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    if ($moderationState != null) $qb->field('moderationState')->equals($moderationState);
    else $qb->field('moderationState')->notIn([ModerationState::NotNeeded]);
    $qb->field('status')->gte(ElementStatus::PendingModification);
    if ($getCount) $qb->count();

    return $qb->getQuery()->execute();
  }

  public function findValidated($getCount = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $qb->field('status')->gt(ElementStatus::PendingAdd);
    if ($getCount) $qb->count();

    return $qb->getQuery()->execute();
  }

  public function findVisibles($getCount = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $qb = $this->filterVisibles($qb);
    if ($getCount) $qb->count();

    return $qb->getQuery()->execute();
  }

  public function findAllPublics($getFullRepresentation, $isAdmin, $limit = null)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');

    $qb = $this->filterVisibles($qb);
    $qb->field('moderationState')->equals(ModerationState::NotNeeded);
    if ($limit) $qb->limit($limit);  

    $this->selectJson($qb, $getFullRepresentation, $isAdmin);  
    
    return $this->queryToArray($qb);
  }  

  public function findAllElements($limit = null, $skip = null, $getCount = false)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
    
    if ($limit) $qb->limit($limit);
    if ($skip) $qb->skip($skip);
    if ($getCount) $qb->count();

    return $qb->getQuery()->execute();
  }

  private function queryToArray($qb)
  {
    return $qb->hydrate(false)->getQuery()->execute()->toArray();
  }

  private function filterVisibles($qb, $status = ElementStatus::PendingModification)
  {
    // fetching pendings and validated
    $qb->field('status')->gte($status);
    // removing element withtout category or withtout geolocation
    $qb->field('moderationState')->notIn(array(ModerationState::GeolocError, ModerationState::NoOptionProvided));
    return $qb;
  }

  private function selectJson($qb, $getFullRepresentation, $isAdmin)
  {
    // get json representation
    if ($getFullRepresentation == 'true') 
    {
      $qb->select('fullJson'); 
      if ($isAdmin) $qb->select('adminJson');
    }
    else
    {
      $qb->select('compactJson');   
    } 
  }

  public function findElementsOwnedBy($userEmail)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
    
    $qb->field('userOwnerEmail')->equals($userEmail);
    $qb->field('status')->notEqual(ElementStatus::ModifiedPendingVersion);
    $qb->sort('updatedAt', 'DESC');
    return $qb->getQuery()->execute();
  }

  public function findWithinCenterFromDate($lat, $lng, $distance, $date, $limit = null)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
    $radius = $distance / 110;
    $qb->field('geo')->withinCenter((float)$lat, (float)$lng, $radius);
    $qb->field('createdAt')->gt($date);
    $qb = $this->filterVisibles($qb);
    if ($limit) $qb->limit($limit);
    return $qb->getQuery()->execute();
  }

  public function findStampedWithId($stampId)
  {
    $qb = $this->createQueryBuilder('BiopenGeoDirectoryBundle:Element');
    $qb->field('stamps.id')->in(array((float) $stampId));
    $qb->select('id');
    return $this->queryToArray($qb);
  }
}


