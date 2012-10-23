<?php
/**
 * This file is part of trekksoft/taggable-zend-memcached-backend.
 *
 * (c) TrekkSoft AG (www.trekksoft.com)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * @author Philippe Gerber <philippe@bigwhoop.ch>
 */
namespace TrekkSoft\Cache;

use Zend_Cache,
    Zend_Cache_Backend_Memcached;

/**
 * Zend_Cache backend for memcached (not libmemcached) with support for tags
 */
class TaggableZendMemcachedBackend extends Zend_Cache_Backend_Memcached
{
    const TAG_NAME_FORMAT = 'tag___%s';
    const TAG_LIFE_TIME   = '1 day';
    
    
    /**
     * @var string
     */
    private $prefixKey = '';


    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        if (array_key_exists('prefix_key', $options)) {
            $this->prefixKey = (string)$options['prefix_key'];
        }
        
        parent::__construct($options);
    }


    /**
     * @param string $id
     * @param bool $doNotTestCacheValidity
     * @return false|string
     */
    public function load($id, $doNotTestCacheValidity = false)
    {
        return parent::load($this->normalizeId($id), $doNotTestCacheValidity);
    }


    /**
     * @param string $id
     * @return false|mixed
     */
    public function test($id)
    {
        return parent::test($this->normalizeId($id));
    }


    /**
     * @param string $data
     * @param string $id
     * @param array $tags
     * @param bool $specificLifetime
     * @return bool
     */
    public function save($data, $id, $tags = array(), $specificLifetime = false)
    {
        $this->storeTagsForId($id, $tags);
        return parent::save($data, $this->normalizeId($id), array(), $specificLifetime);
    }


    /**
     * @param string $id
     * @return bool
     */
    public function remove($id)
    {
        return parent::remove($this->normalizeId($id));
    }


    /**
     * @param string $id
     * @return array
     */
    public function getMetadatas($id)
    {
        return parent::getMetadatas($this->normalizeId($id));
    }


    /**
     * @param string $id
     * @param int $extraLifetime
     * @return bool
     */
    public function touch($id, $extraLifetime)
    {
        return parent::touch($this->normalizeId($id), $extraLifetime);
    }


    /**
     * @param string $id
     * @return string
     */
    private function normalizeId($id)
    {
        return $this->prefixKey . $id;
    }


    /**
     * @param string $mode
     * @param array $tags
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
        switch ($mode)
        {
            // Remove all entries that match all tags
            case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                foreach ($this->getIdsMatchingTags($tags) as $entryId) {
                    $this->remove($entryId);
                    $this->removeIdFromTags($tags, $entryId);
                }
                break;
                
            // Remove all entries that match at least one of the tags
            case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                foreach ($this->getIdsMatchingAnyTags($tags) as $entryId) {
                    $this->remove($entryId);
                    $this->removeIdFromTags($tags, $entryId);
                }
                break;
            
            default:
                parent::clean($mode, $tags);
                break;
        }
    }
    

    /**
     * Return an array of stored tags
     *
     * @return array array of stored tags (string)
     */
    public function getTags()
    {
        $this->_log(self::TAGS_UNSUPPORTED_BY_SAVE_OF_MEMCACHED_BACKEND);
        return array();
    }


    /**
     * @param array $tags
     * @return array
     */
    public function getIdsMatchingTags($tags = array())
    {
        $entryIds = array();
                        
        foreach ($tags as $tag) {
            $tagIds = $this->getIdsForTag($tag);
            
            foreach ($tagIds as $idInTag) {
                if (array_key_exists($idInTag, $entryIds)) {
                    $entryIds[$idInTag]++;
                } else {
                    $entryIds[$idInTag] = 1;
                }
            }
        }
        
        $numTags = count($tags);
        foreach ($entryIds as $entryId => $useCount) {
            if ($useCount !== $numTags) {
                unset($entryIds[$entryId]);
            }
        }
        
        return array_keys($entryIds);
    }


    /**
     * @param array $tags
     * @return array
     */
    public function getIdsMatchingAnyTags($tags = array())
    {
        $entryIds = array();
        
        foreach ($tags as $tag) {
            $tagIds = $this->getIdsForTag($tag);
            $entryIds = array_merge($entryIds, $tagIds);
        }
        
        return array_unique($entryIds);
    }


    /**
     * @param string $tag
     * @return array
     */
    private function getIdsForTag($tag)
    {
        $ids = $this->load($this->formatTagId($tag));
        
        if (empty($ids)) {
            return array();
        }
        
        return (array)unserialize((string)$ids);
    }


    /**
     * @param string $entryId
     * @param array $tags
     */
    private function storeTagsForId($entryId, array $tags)
    {
        foreach ($tags as $tag) {
            $idsInTag = $this->getIdsForTag($tag);
            
            if (!in_array($entryId, $idsInTag)) {
                $idsInTag[] = $entryId;
                
                $tagId    = $this->formatTagId($tag);
                $tagValue = serialize($idsInTag);
                
                $this->save($tagValue, $tagId, array(), $this->getTagLifeTime());
            }
        }
    }


    /**
     * @param array $tags
     * @param string $entryId
     */
    private function removeIdFromTags(array $tags, $entryId)
    {
        foreach ($tags as $tag) {
            $this->removeIdFromTag($tag, $entryId);
        }
    }


    /**
     * @param string $tag
     * @param string $entryId
     */
    private function removeIdFromTag($tag, $entryId)
    {
        $tagId    = $this->formatTagId($tag);
        $idsInTag = $this->getIdsForTag($tag);
        
        $idxOfEntryId = array_search($entryId, $idsInTag);
        if ($idxOfEntryId > -1) {
            unset($idsInTag[$idxOfEntryId]);
            
            $tagValue = serialize($idsInTag);
            $this->save($tagValue, $tagId, array(), $this->getTagLifeTime());
        }
    }


    /**
     * @param string $tag
     * @return string
     */
    private function formatTagId($tag)
    {
        return sprintf(self::TAG_NAME_FORMAT, $tag);
    }
    
    
    /**
     * @return array
     */
    public function getCapabilities()
    {
        $caps = parent::getCapabilities();
        $caps['tags'] = true;
        
        return $caps;
    }


    /**
     * @return int
     */
    private function getTagLifeTime()
    {
        return strtotime(self::TAG_LIFE_TIME, 0);
    }
}
