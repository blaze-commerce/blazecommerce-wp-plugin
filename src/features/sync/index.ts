import { TypesenseSyncInterface } from "./interface-sync";

export class TypesenseSync {
  static init(collections: TypesenseSyncInterface[]) {
    console.log('collections', collections);
    
    collections.forEach(collection => {
      jQuery(document.body).on('click', collection.linkId, collection.onSync);
    })
  }
}
