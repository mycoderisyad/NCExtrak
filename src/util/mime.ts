const archiveMimeSet = new Set<string>([
  'application/zip',
  'application/x-zip-compressed',
  'application/x-rar',
  'application/vnd.rar',
  'application/x-rar-compressed',
  'application/x-7z-compressed',
  'application/x-tar',
  'application/gzip',
  'application/x-gzip',
  'application/x-bzip2',
  'application/octet-stream',
])

export const isArchiveMime = (mimeType: string | null | undefined): boolean => {
  if (!mimeType) {
    return false
  }
  return archiveMimeSet.has(mimeType.toLowerCase())
}
