<?php

/**
 * @category    YourCategory
 * @since       01/02/2026
 * @author      Your Name <your.email@example.com>
 * @copyright   Copyright (c) 2026 YourCompany (https://yourcompany.com/)
 */

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Carbon\Carbon;

/**
 * Migration to create sample data objects.
 *
 * This migration creates 100 objects for each Pimcore class defined in the system.
 *
 * @author Your Development Team
 */
final class Version20260201000000 extends AbstractMigration
{
    private array $createdObjects = [];
    
    /**
     * Disable transactional execution for this migration
     * because we're using Pimcore ORM which manages its own transactions
     */
    public function isTransactional(): bool
    {
        return false;
    }
    
    /**
     * Returns a description of the migration.
     *
     * @return string Description of what this migration does
     */
    public function getDescription(): string
    {
        return 'Create 100 sample objects for each Pimcore class';
    }

    /**
     * Executes the migration up.
     *
     * Creates 100 objects for each class:
     * - Customer
     * - Company
     * - Accommodation
     * - Unit
     * - Reservation
     * - And all other defined classes
     *
     * @param Schema $schema The database schema to modify
     */
    public function up(Schema $schema): void
    {
        $this->write('Starting to create sample objects for all classes...');
        
        // Get all class definitions
        $classList = new ClassDefinition\Listing();
        $classList->setOrder('ASC');
        $classList->setOrderKey('name');
        $classes = $classList->load();
        
        // First pass: Create all objects without relations
        foreach ($classes as $class) {
            $className = $class->getName();
            $classFullName = 'Pimcore\\Model\\DataObject\\' . $className;
            
            // Skip if class doesn't exist
            if (!class_exists($classFullName)) {
                $this->write(sprintf('Skipping class %s - class not found', $className));
                continue;
            }
            
            $this->write(sprintf('Creating 10 objects for class: %s', $className));
            
            // Create parent folder for this class under root (ID: 1)
            $folderKey = 'SampleData_' . $className;
            $parentFolder = null;
            
            try {
                // Try to get existing folder by checking if it exists
                $listing = new DataObject\Listing();
                $listing->setCondition('type = "folder" AND parentId = 1 AND `key` = ?', [$folderKey]);
                $listing->setLimit(1);
                $folders = $listing->load();
                
                if (!empty($folders)) {
                    $parentFolder = $folders[0];
                } else {
                    // Create new folder
                    $parentFolder = new DataObject\Folder();
                    $parentFolder->setParentId(1);
                    $parentFolder->setKey($folderKey);
                    $parentFolder->save();
                }
            } catch (\Exception $e) {
                $this->write(sprintf('Could not create/find folder for %s: %s. Using root folder.', $className, $e->getMessage()));
            }
            
            // Determine parent ID to use
            $parentId = $parentFolder ? $parentFolder->getId() : 1;
            
            // Initialize array for this class
            $this->createdObjects[$className] = [];
            
            // Create 100 objects
            for ($i = 1; $i <= 10; $i++) {
                try {
                    $object = new $classFullName();
                    $object->setKey(sprintf('%s_%03d', $className, $i));
                    $object->setParentId($parentId);
                    $object->setPublished(true);
                    
                    // Set sample data based on class (without relations)
                    $this->populateObjectData($object, $className, $i, false);
                    
                    $object->save();
                    
                    // Store reference for relation linking
                    $this->createdObjects[$className][$i] = $object;
                } catch (\Exception $e) {
                    $this->write(sprintf('Error creating %s #%d: %s', $className, $i, $e->getMessage()));
                }
            }
            
            $this->write(sprintf('Completed creating objects for class: %s', $className));
        }
        
        // Second pass: Add relations
        $this->write('Adding relations between objects...');
        foreach ($this->createdObjects as $className => $objects) {
            foreach ($objects as $i => $object) {
                try {
                    $this->populateObjectData($object, $className, $i, true);
                    $object->save();
                } catch (\Exception $e) {
                    $this->write(sprintf('Error adding relations to %s #%d: %s', $className, $i, $e->getMessage()));
                }
            }
        }
        
        $this->write('Finished creating sample objects for all classes.');
    }
    
    /**
     * Populate object with sample data based on class type
     *
     * @param DataObject\Concrete $object The object to populate
     * @param string $className The class name
     * @param int $index The object index
     * @param bool $addRelations Whether to add relations (second pass)
     */
    private function populateObjectData(DataObject\Concrete $object, string $className, int $index, bool $addRelations): void
    {
        if ($addRelations) {
            // Second pass: Add relations only
            $this->addRelationsToObject($object, $className, $index);
            return;
        }
        
        // First pass: Basic data without relations
        switch ($className) {
            case 'Customer':
                $object->setFirstname('Customer' . $index);
                $object->setLastname('User' . $index);
                $object->setGender($index % 2 === 0 ? 'male' : 'female');
                $object->setEmail(sprintf('customer%d@example.com', $index));
                $object->setPassword('password123');
                break;
                
            case 'Company':
                $object->setCompanyName('Company ' . $index);
                $object->setEmail(sprintf('company%d@example.com', $index));
                $object->setPassword('password123');
                $object->setPhoneNumber(1000000000 + $index);
                break;
                
            case 'Accommodation':
                $object->setName('Accommodation ' . $index);
                break;
                
            case 'Unit':
                if (method_exists($object, 'setNumber')) {
                    $object->setNumber($index);
                }
                if (method_exists($object, 'setNumberOfRooms')) {
                    $object->setNumberOfRooms(($index % 5) + 1);
                }
                if (method_exists($object, 'setNumberOfBeds')) {
                    $object->setNumberOfBeds(($index % 4) + 1);
                }
                if (method_exists($object, 'setNumberOfBathrooms')) {
                    $object->setNumberOfBathrooms(($index % 3) + 1);
                }
                break;
                
            case 'Reservation':
                if (method_exists($object, 'setTitle')) {
                    $object->setTitle('Reservation ' . $index);
                }
                if (method_exists($object, 'setDescription')) {
                    $object->setDescription('Description for reservation ' . $index);
                }
                if (method_exists($object, 'setReservationType')) {
                    $types = ['Hotel', 'Appartement'];
                    $object->setReservationType($types[array_rand($types)]);
                }
                if (method_exists($object, 'setAvailabeReservation')) {
                    $object->setAvailabeReservation(true);
                }
                break;
                
            case 'CustomerBookedReservations':
                if (method_exists($object, 'setPaidPrice')) {
                    $object->setPaidPrice(500 + ($index * 50));
                }
                if (method_exists($object, 'setDate')) {
                    $object->setDate(Carbon::now());
                }
                break;
                
            case 'CustomerPayment':
                if (method_exists($object, 'setTotalPrice')) {
                    $object->setTotalPrice(500 + ($index * 50));
                }
                if (method_exists($object, 'setDate')) {
                    $object->setDate(Carbon::now()->addDays($index));
                }
                if (method_exists($object, 'setIsPaid')) {
                    $object->setIsPaid($index % 2 === 0);
                }
                if (method_exists($object, 'setInstalmentsNumber')) {
                    $object->setInstalmentsNumber(($index % 12) + 1);
                }
                break;
                
            case 'CustomerInstalments':

                if (method_exists($object, 'setPrice')) {
                    $object->setPrice(100 + ($index * 10));
                }
                if (method_exists($object, 'setInstalmentDate')) {
                    $object->setInstalmentDate(Carbon::now());
                }
                break;
                
            case 'ReservationInternalTrip':
                if (method_exists($object, 'setName')) {
                    $object->setName('Trip ' . $index);
                }
                if (method_exists($object, 'setPrice')) {
                    $object->setPrice(100 + ($index * 10));
                }
                if (method_exists($object, 'setDescription')) {
                    $object->setDescription('Trip description ' . $index);
                }
                break;
                
            case 'ReservationReviews':
                if (method_exists($object, 'setRating')) {
                    $object->setRating(($index % 5) + 1);
                }
                if (method_exists($object, 'setComment')) {
                    $object->setComment('Sample review comment ' . $index);
                }
                break;
                
            case 'UnitUtilities':
                if (method_exists($object, 'setIsWifi')) {
                    $object->setIsWifi($index % 2 === 0);
                }
                if (method_exists($object, 'setIsTv')) {
                    $object->setIsTv($index % 3 === 0);
                }
                if (method_exists($object, 'setIsAirConditioner')) {
                    $object->setIsAirConditioner($index % 2 === 1);
                }
                break;
                
            default:
                // For other classes, try to set basic fields if they exist
                if (method_exists($object, 'setName')) {
                    $object->setName($className . ' ' . $index);
                }
                break;
        }
    }
    
    /**
     * Add relations to an object
     *
     * @param DataObject\Concrete $object The object to add relations to
     * @param string $className The class name
     * @param int $index The object index
     */
    private function addRelationsToObject(DataObject\Concrete $object, string $className, int $index): void
    {
        switch ($className) {
            case 'CustomerBookedReservations':
                // Link to Customer and Reservation (manyToOneRelation - single objects)
                if (method_exists($object, 'setCustomer') && isset($this->createdObjects['Customer'])) {
                    $customerIndex = (($index - 1) % count($this->createdObjects['Customer'])) + 1;
                    if (isset($this->createdObjects['Customer'][$customerIndex])) {
                        $object->setCustomer($this->createdObjects['Customer'][$customerIndex]);
                    }
                }
                if (method_exists($object, 'setReservation') && isset($this->createdObjects['Reservation'])) {
                    $reservationIndex = (($index - 1) % count($this->createdObjects['Reservation'])) + 1;
                    if (isset($this->createdObjects['Reservation'][$reservationIndex])) {
                        $object->setReservation($this->createdObjects['Reservation'][$reservationIndex]);
                    }
                }
                break;
                
            case 'Reservation':
                // Link to Company (manyToOneRelation - single object)
                if (method_exists($object, 'setCompany') && isset($this->createdObjects['Company'])) {
                    $companyIndex = (($index - 1) % count($this->createdObjects['Company'])) + 1;
                    if (isset($this->createdObjects['Company'][$companyIndex])) {
                        $object->setCompany($this->createdObjects['Company'][$companyIndex]);
                    }
                }
                break;
                
            case 'Unit':
                // Link to Accommodation (manyToOneRelation - single object)
                if (method_exists($object, 'setAccommodation') && isset($this->createdObjects['Accommodation'])) {
                    $accommodationIndex = (($index - 1) % count($this->createdObjects['Accommodation'])) + 1;
                    if (isset($this->createdObjects['Accommodation'][$accommodationIndex])) {
                        $object->setAccommodation($this->createdObjects['Accommodation'][$accommodationIndex]);
                    }
                }
                break;
                
            case 'Accommodation':
                // Link to Reservation (manyToOneRelation - single object)
                if (method_exists($object, 'setReservation') && isset($this->createdObjects['Reservation'])) {
                    $reservationIndex = (($index - 1) % count($this->createdObjects['Reservation'])) + 1;
                    if (isset($this->createdObjects['Reservation'][$reservationIndex])) {
                        $object->setReservation($this->createdObjects['Reservation'][$reservationIndex]);
                    }
                }
                break;
                
            case 'UnitUtilities':
                // Link to Unit (manyToManyObjectRelation - array of objects)
                if (method_exists($object, 'setUnit') && isset($this->createdObjects['Unit'])) {
                    $units = [];
                    // Link to 1-3 units
                    for ($i = 0; $i < min(3, count($this->createdObjects['Unit'])); $i++) {
                        $unitIndex = ((($index + $i) - 1) % count($this->createdObjects['Unit'])) + 1;
                        if (isset($this->createdObjects['Unit'][$unitIndex])) {
                            $units[] = $this->createdObjects['Unit'][$unitIndex];
                        }
                    }
                    if (!empty($units)) {
                        $object->setUnit($units);
                    }
                }
                break;
                
            case 'ReservationReviews':
                // Link to Reservation and Customer (manyToOneRelation - single objects)
                if (method_exists($object, 'setReservation') && isset($this->createdObjects['Reservation'])) {
                    $reservationIndex = (($index - 1) % count($this->createdObjects['Reservation'])) + 1;
                    if (isset($this->createdObjects['Reservation'][$reservationIndex])) {
                        $object->setReservation($this->createdObjects['Reservation'][$reservationIndex]);
                    }
                }
                if (method_exists($object, 'setCustomer') && isset($this->createdObjects['Customer'])) {
                    $customerIndex = (($index - 1) % count($this->createdObjects['Customer'])) + 1;
                    if (isset($this->createdObjects['Customer'][$customerIndex])) {
                        $object->setCustomer($this->createdObjects['Customer'][$customerIndex]);
                    }
                }
                break;
                
            case 'ReservationInternalTrip':
                // Link to Reservation (manyToOneRelation - single object)
                if (method_exists($object, 'setReservation') && isset($this->createdObjects['Reservation'])) {
                    $reservationIndex = (($index - 1) % count($this->createdObjects['Reservation'])) + 1;
                    if (isset($this->createdObjects['Reservation'][$reservationIndex])) {
                        $object->setReservation($this->createdObjects['Reservation'][$reservationIndex]);
                    }
                }
                break;
                
            case 'CustomerPayment':
                // Link to allowed class for customer relation (configured as CustomerPayment)
                if (method_exists($object, 'setCustomer') && isset($this->createdObjects['CustomerPayment'])) {
                    $paymentIndex = (($index - 1) % count($this->createdObjects['CustomerPayment'])) + 1;
                    if (isset($this->createdObjects['CustomerPayment'][$paymentIndex])) {
                        $object->setCustomer($this->createdObjects['CustomerPayment'][$paymentIndex]);
                    }
                }
                break;
                
            case 'CustomerInstalments':
                // Link to Customer (manyToOneRelation - single object)
                if (method_exists($object, 'setCustomer') && isset($this->createdObjects['Customer'])) {
                    $customerIndex = (($index - 1) % count($this->createdObjects['Customer'])) + 1;
                    if (isset($this->createdObjects['Customer'][$customerIndex])) {
                        $object->setCustomer($this->createdObjects['Customer'][$customerIndex]);
                    }
                }
                break;
        }
    }

    /**
     * Executes the migration down.
     *
     * This migration cannot be reversed as data has been permanently deleted.
     *
     * @param Schema $schema The database schema to modify
     */
    public function down(Schema $schema): void
    {
        // Disable foreign key checks to allow truncating tables with foreign key constraints
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');

        // Truncate main objects table
        $this->addSql('TRUNCATE TABLE objects');

        // Get all tables that match Pimcore object patterns
        $connection = $this->connection;
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        foreach ($tables as $table) {
            // Truncate object store tables (object_store_*)
            if (str_starts_with($table, 'object_store_')) {
                $this->addSql(sprintf('TRUNCATE TABLE %s', $table));
            }
            
            // Truncate object query tables (object_query_*)
            if (str_starts_with($table, 'object_query_')) {
                $this->addSql(sprintf('TRUNCATE TABLE %s', $table));
            }
            
            // Truncate object localized tables (object_localized_*)
            if (str_starts_with($table, 'object_localized_')) {
                $this->addSql(sprintf('TRUNCATE TABLE %s', $table));
            }
            
            // Truncate object relation tables (object_relations_*)
            if (str_starts_with($table, 'object_relations_')) {
                $this->addSql(sprintf('TRUNCATE TABLE %s', $table));
            }
            
            // Truncate object collection tables (object_collection_*)
            if (str_starts_with($table, 'object_collection_')) {
                $this->addSql(sprintf('TRUNCATE TABLE %s', $table));
            }
            
            // Truncate object metadata tables (object_metadata_*)
            if (str_starts_with($table, 'object_metadata_')) {
                $this->addSql(sprintf('TRUNCATE TABLE %s', $table));
            }
        }

        // Truncate object dependencies
        $this->addSql('DELETE FROM dependencies WHERE sourcetype = "object" OR targettype = "object"');
        
        // Truncate tree locks related to objects
        $this->addSql('DELETE FROM tree_locks WHERE type = "object"');

        // Re-insert the root folder record
        $timestamp = time();
        $this->addSql(sprintf(
            'INSERT INTO objects (id, parentId, type, `key`, path, `index`, published, creationDate, modificationDate, userOwner, userModification, classId, className, childrenSortBy, childrenSortOrder, versionCount) VALUES (1, 0, "folder", "", "/", 999999, 1, %d, %d, 1, 1, NULL, NULL, NULL, NULL, 0)',
            $timestamp,
            $timestamp
        ));

        // Re-enable foreign key checks
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}