import { showError, showSuccess } from '@nextcloud/dialogs'
import { FileAction, Permission, registerFileAction, type Node } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'

import { getExtractJobStatus, requestExtract } from '../api/extractClient'
import { isArchiveMime } from '../util/mime'

const EXTRACT_ICON = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
  <path d="M5 3h11l5 5v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm10 1.5V9h4.5"/>
  <path d="M12 11v6m0 0l-3-3m3 3l3-3"/>
</svg>
`.trim()

const archiveNamePattern = /\.(zip|rar|7z|tar|tgz|tbz2|gz|bz2)$/i

const isArchiveCandidate = (node: Node): boolean => {
  if (isArchiveMime(node.mime)) {
    return true
  }
  return archiveNamePattern.test(node.basename ?? '')
}

const canWriteSibling = (node: Node): boolean => {
  const perms = typeof node.permissions === 'number' ? node.permissions : 0
  return (perms & Permission.CREATE) !== 0 || (perms & Permission.UPDATE) !== 0
}

const refreshFileView = (): void => {
  const globalObject = window as Window & {
    OCA?: {
      Files?: {
        App?: {
          fileList?: { reload?: () => void }
          currentFileList?: { reload?: () => void }
        }
        fileList?: { reload?: () => void }
      }
    }
  }

  const filesApp = globalObject.OCA?.Files
  filesApp?.App?.currentFileList?.reload?.()
  filesApp?.App?.fileList?.reload?.()
  filesApp?.fileList?.reload?.()
}

const pollJobUntilFinished = async (jobId: number): Promise<void> => {
  const maxAttempts = 600
  const delayMs = 2000

  for (let attempt = 0; attempt < maxAttempts; attempt++) {
    await new Promise((resolve) => window.setTimeout(resolve, delayMs))
    const status = await getExtractJobStatus(jobId)
    if (status.state === 'done') {
      showSuccess(
        t('ncextrak', 'Archive extracted to {folder}', {
          folder: status.result?.targetFolder ?? '',
        }),
      )
      refreshFileView()
      return
    }
    if (status.state === 'failed') {
      showError(t('ncextrak', 'Archive extraction failed: {error}', { error: status.error ?? '' }))
      return
    }
  }
}

export const registerExtractAction = (): void => {
  const action = new FileAction({
    id: 'ncextrak-extract',
    displayName: () => t('ncextrak', 'Extract here'),
    iconSvgInline: () => EXTRACT_ICON,
    order: 25,
    enabled: (files) => {
      if (files.length !== 1) {
        return false
      }
      const node = files[0]
      return isArchiveCandidate(node) && canWriteSibling(node)
    },
    exec: async (file) => {
      const fileId = typeof file.fileid === 'number' ? file.fileid : 0
      if (fileId <= 0) {
        showError(t('ncextrak', 'Unable to resolve target file ID'))
        return null
      }

      try {
        const result = await requestExtract(fileId)
        if (result.mode === 'sync' && result.result) {
          showSuccess(
            t('ncextrak', 'Extracted to {folder}', { folder: result.result.targetFolder }),
          )
          refreshFileView()
          return true
        }

        if (result.mode === 'async' && typeof result.jobId === 'number') {
          showSuccess(t('ncextrak', 'Extraction queued in background'))
          void pollJobUntilFinished(result.jobId)
          return true
        }
      } catch {
        showError(t('ncextrak', 'Archive extraction failed'))
        return false
      }

      return null
    },
  })

  registerFileAction(action)
}
