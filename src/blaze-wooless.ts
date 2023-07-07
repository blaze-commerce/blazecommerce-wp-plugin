import { TypesenseSync } from "./features/sync";
import { ProductSync } from "./features/sync/product-sync";

export class BlazeWooless {
  static init() {
    // this.registerEvents();

    TypesenseSync.init([
      // new ProductSync(),
    ])

    // this.body.find( '.wooless-multiple-select' ).chosen();
  }
}
