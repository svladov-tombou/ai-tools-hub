import type common from "../../messages/bg/common.json";

declare module "next-intl" {
  interface AppConfig {
    Messages: {
      common: typeof common;
    };
  }
}
