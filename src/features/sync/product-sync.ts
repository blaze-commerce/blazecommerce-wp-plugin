import { TypesenseSyncInterface } from "./interface-sync";

export class ProductSync implements TypesenseSyncInterface {
  linkId = '#sync-product-link';
  onSync = (e: JQuery.ClickEvent) => {
    e.preventDefault();
    console.log(`On click for ${this.linkId}`);
  }
}
