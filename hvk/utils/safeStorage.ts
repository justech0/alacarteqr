const getStorage = (): Storage | null => {
  if (typeof window === 'undefined') return null;

  const tryStorage = (store: Storage | undefined | null) => {
    if (!store) return null;
    try {
      const testKey = '__saray_storage_test__';
      store.setItem(testKey, '1');
      store.removeItem(testKey);
      return store;
    } catch {
      return null;
    }
  };

  return tryStorage(window.localStorage) ?? tryStorage(window.sessionStorage);
};

export const safeGetItem = (key: string): string | null => {
  try {
    const store = getStorage();
    return store ? store.getItem(key) : null;
  } catch {
    return null;
  }
};

export const safeSetItem = (key: string, value: string): void => {
  try {
    const store = getStorage();
    if (!store) return;
    store.setItem(key, value);
  } catch {
    // storage unavailable; ignore
  }
};

export const safeRemoveItem = (key: string): void => {
  try {
    const store = getStorage();
    if (!store) return;
    store.removeItem(key);
  } catch {
    // ignore
  }
};
