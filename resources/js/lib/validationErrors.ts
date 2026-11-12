import type { ValidationErrors } from "@/types/pages";

function toSnakeCase(value: string): string {
  return value.replace(/([a-z0-9])([A-Z])/g, "$1_$2").toLowerCase();
}

function toCamelCase(value: string): string {
  return value.replace(/_([a-z])/g, (_, char: string) => char.toUpperCase());
}

function resolveFieldKeys(field: string): string[] {
  const keys = new Set<string>([field, toSnakeCase(field), toCamelCase(field)]);

  return [...keys];
}

function collectRawFieldErrors(
  errors: ValidationErrors | undefined,
  field: string,
): string[] {
  if (!errors) {
    return [];
  }

  const result: string[] = [];

  for (const key of resolveFieldKeys(field)) {
    const messages = errors[key];
    if (!Array.isArray(messages)) {
      continue;
    }

    for (const message of messages) {
      if (typeof message === "string") {
        result.push(message);
      }
    }
  }

  return result;
}

export function fieldErrors(
  errors: ValidationErrors | undefined,
  field: string,
): string[] {
  const unique = new Set<string>();

  for (const message of collectRawFieldErrors(errors, field)) {
    const normalized = message.trim();
    if (normalized.length > 0) {
      unique.add(normalized);
    }
  }

  return [...unique];
}

export function hasFieldErrors(
  errors: ValidationErrors | undefined,
  field: string,
): boolean {
  return collectRawFieldErrors(errors, field).length > 0;
}
