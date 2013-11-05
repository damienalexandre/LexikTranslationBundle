<?php

namespace Lexik\Bundle\TranslationBundle\Translation\Importer;

use Doctrine\Common\Persistence\ObjectManager;

use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Translation\Loader\XliffLoader;
use Lexik\Bundle\TranslationBundle\Document\TransUnit as TransUnitDocument;
use Lexik\Bundle\TranslationBundle\Model\TransUnit;
use Lexik\Bundle\TranslationBundle\Model\Translation;
use Lexik\Bundle\TranslationBundle\Translation\Manager\FileManager;
use Lexik\Bundle\TranslationBundle\Translation\Manager\TransUnitManager;

/**
 * Import a translation file into the database.
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class FileImporter
{
    /**
     * @var array
     */
    private $loaders;

    /**
     * @var Doctrine\Common\Persistence\ObjectManager
     */
    private $om;

    /**
     * @var Lexik\Bundle\TranslationBundle\Translation\Manager\TransUnitManager
     */
    private $transUnitManager;

    /**
     * @var Lexik\Bundle\TranslationBundle\Translation\Manager\FileManager
     */
    private $fileManager;

    /**
     * Construct.
     *
     * @param array $loaders
     * @param ObjectManager $om
     * @param TransUnitManager $transUnitManager
     * @param FileManager $fileManager
     */
    public function __construct(array $loaders, ObjectManager $om, TransUnitManager $transUnitManager, FileManager $fileManager)
    {
        $this->loaders = $loaders;
        $this->om = $om;
        $this->transUnitManager = $transUnitManager;
        $this->fileManager = $fileManager;
    }

    /**
     * Import the given file and return the number of inserted translations.
     *
     * @param \Symfony\Component\Finder\SplFileInfo $file
     * @return int
     */
    public function import(\Symfony\Component\Finder\SplFileInfo $file)
    {
        $imported = 0;
        list($domain, $locale, $extention) = explode('.', $file->getFilename());
        $serviceId = sprintf('translation.loader.%s', $extention);

        $jms_loader = new XliffLoader();

        $messageCatalogue = $jms_loader->load($file->getPathname(), $locale, $domain);

        $translationFile = $this->fileManager->getFor($file->getFilename(), $file->getPath());


        foreach ($messageCatalogue->getDomain($domain)->all() as $key => $content)
        {
            $transUnit = $this->transUnitManager->findOneByKeyAndDomain($key, $domain);

            if (!($transUnit instanceof TransUnit)) {
                $transUnit = $this->transUnitManager->create($key, $domain);

                /** @var $content Message */
                $transUnit->setFiles(implode(', ', $content->getSources()));
                $transUnit->setHint($content->getLocaleString());
            }

            $translation = $this->transUnitManager->addTranslation($transUnit, $locale, $content->getLocaleString(), $translationFile);
            if ($translation instanceof Translation) {
                $imported++;
            }

            // convert MongoTimestamp objects to time to don't get an error in:
            // Doctrine\ODM\MongoDB\Mapping\Types\TimestampType::convertToDatabaseValue()
            if ($transUnit instanceof TransUnitDocument) {
                $transUnit->convertMongoTimestamp();
            }
        }

        $this->om->flush();
        $this->om->clear();


        return $imported;
    }
}
