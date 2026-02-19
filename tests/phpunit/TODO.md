# Test which must be written

## SepaDebitMandatesController

-  SepaDebitMandatesController::mandateStore()

## ProjectParticipantsStorage

Acutally all database storage classes which call into
DatabaseStorageFolder::addDocument(). Difficult, needs to instantiate
stubs for the dependencies of the base class OC\Files\Storage\Common.

- ProjectParticipantsStorage::addDebitMandate()

## File-upload controller need testing

## ProjectParticipantsController and MailingListsService
