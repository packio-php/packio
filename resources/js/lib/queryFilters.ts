export type QueryValue =
  | boolean
  | number
  | string
  | string[]
  | null
  | undefined;

export function cleanQuery(
  query: Record<string, QueryValue>,
): Record<string, boolean | number | string | string[]> {
  const cleaned: Record<string, boolean | number | string | string[]> = {};

  for (const [key, value] of Object.entries(query)) {
    if (Array.isArray(value)) {
      if (value.length > 0) {
        cleaned[key] = value;
      }
      continue;
    }

    if (typeof value === "boolean") {
      if (value) {
        cleaned[key] = "1";
      }
      continue;
    }

    if (typeof value === "number") {
      cleaned[key] = value;
      continue;
    }

    if (typeof value === "string" && value.trim() !== "") {
      cleaned[key] = value.trim();
    }
  }

  return cleaned;
}

export function queryString(query: Record<string, QueryValue>): string {
  const pairs: string[] = [];

  for (const [key, value] of Object.entries(cleanQuery(query))) {
    if (Array.isArray(value)) {
      for (const item of value) {
        pairs.push(`${encodeURIComponent(key)}[]=${encodeURIComponent(item)}`);
      }
      continue;
    }

    pairs.push(
      `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`,
    );
  }

  return pairs.length === 0 ? "" : `?${pairs.join("&")}`;
}
