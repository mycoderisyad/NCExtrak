import { showError, showSuccess } from '@nextcloud/dialogs'
import {
  Permission,
  registerFileAction,
  type ActionContext,
  type ActionContextSingle,
  type IFileAction,
} from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'

import { getExtractJobStatus, requestExtract } from '../api/extractClient'
import { isArchiveMime } from '../util/mime'

interface FileNodeLike {
  id?: string
  fileid?: number
  mime?: string | null
  permissions?: number
  attributes?: {
    fileid?: number
  }
}

const EXTRACT_ICON = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
	<path d="M5 3h11l5 5v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm10 1.5V9h4.5"/>
	<path d="M12 11v6m0 0l-3-3m3 3l3-3"/>
</svg>
`.trim()

const extractFileId = (node: FileNodeLike): number => {
  const rawId = node.fileid ?? node.attributes?.fileid
  return typeof rawId === 'number' ? rawId : 0
}

const userCanCreateInFolder = (node: FileNodeLike): boolean => {
  if (typeof node.permissions !== 'number') {
    return false
  }

  return (node.permissions & Permission.CREATE) !== 0
}

const refreshFileView = (): void => {
  const globalObject = window as Window & {
    OCA?: {
      Files?: {
        fileList?: {
          reload: () => void
        }
      }
    }
  }

  globalObject.OCA?.Files?.fileList?.reload()
}

const pollJobUntilFinished = async (jobId: number): Promise<void> => {
  const maxAttempts = 120
  const delayMs = 1000

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
  const action: IFileAction = {
    id: 'ncextrak-extract',
    displayName: () => t('ncextrak', 'Extract here'),
    iconSvgInline: () => EXTRACT_ICON,
    order: 25,
    enabled: (context: ActionContext) => {
      if (context.nodes.length !== 1) {
        return false
      }

      const node = context.nodes[0] as unknown as FileNodeLike
      return isArchiveMime(node.mime) && userCanCreateInFolder(node)
    },
    exec: async (context: ActionContextSingle) => {
      const targetNode = context.nodes[0] as unknown as FileNodeLike
      const fileId = extractFileId(targetNode)
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
          return null
        }

        if (result.mode === 'async' && typeof result.jobId === 'number') {
          showSuccess(t('ncextrak', 'Extraction queued in background'))
          void pollJobUntilFinished(result.jobId)
          return null
        }
      } catch {
        showError(t('ncextrak', 'Archive extraction failed'))
        return null
      }

      return null
    },
  }

  registerFileAction(action)
}
