<?php

namespace App\Controller;

use App\Entity\News;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Serializer\SerializerInterface;
use Knp\Component\Pager\PaginatorInterface;

class NewsController extends AbstractController
{
    #[Route('/news', name: 'create_news', methods: ['POST'])] # Task 4
    public function createNews(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ValidatorInterface $validator
    ): JsonResponse {
        $title = $request->request->get('title');
        $author = $request->request->get('author');
        $content = $request->request->get('content');
        $photoFile = $request->files->get('photo');

        if (!$title || !$author || !$content) {
            return new JsonResponse(['error' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
        }

        $news = new News();
        $news->setTitle($title);
        $news->setAuthor($author);
        $news->setContent($content);

        // if photo added
        if ($photoFile) {
            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $photoFile->guessExtension();

            try {
                $photoFile->move(
                    $this->getParameter('news_photos_directory'),
                    $newFilename
                );
            } catch (FileException $e) {
                return new JsonResponse(['error' => 'Could not upload photo'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $news->setPhoto($newFilename);
        }

        // validation
        $errors = $validator->validate($news);
        if (count($errors) > 0) {
            $errorsString = (string) $errors;
            return new JsonResponse(['error' => $errorsString], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($news);
        $em->flush();

        return new JsonResponse(['status' => 'News created'], Response::HTTP_CREATED);
    }

    #[Route('/news/{id}', name: 'delete_news', methods: ['DELETE'])] # Task 5
    public function deleteNews(int $id, EntityManagerInterface $em): JsonResponse
    {
        $news = $em->getRepository(News::class)->find($id);

        if (!$news) {
            return new JsonResponse(['error' => 'News not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $em->remove($news);
        $em->flush();

        return new JsonResponse(['status' => 'News deleted'], JsonResponse::HTTP_OK);
    }

    #[Route('/news', name: 'get_news', methods: ['GET'])] # Task 6
    public function getNews(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator,
        SerializerInterface $serializer
    ): JsonResponse {
        // params for pagination
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        // params for filters
        $title = $request->query->get('title');
        $author = $request->query->get('author');

        $queryBuilder = $em->getRepository(News::class)->createQueryBuilder('n');

        if ($title) {
            $queryBuilder->andWhere('n.title LIKE :title')
                ->setParameter('title', '%' . $title . '%');
        }

        if ($author) {
            $queryBuilder->andWhere('n.author LIKE :author')
                ->setParameter('author', '%' . $author . '%');
        }

        $query = $queryBuilder->getQuery();

        // pagination
        $pagination = $paginator->paginate(
            $query,
            $page,
            $limit
        );

        // serialization: php -> json
        $newsList = $serializer->serialize($pagination->getItems(), 'json');

        return new JsonResponse([
            'data' => json_decode($newsList),
            'total' => $pagination->getTotalItemCount(),
            'current_page' => $page,
            'total_pages' => ceil($pagination->getTotalItemCount() / $limit)
        ]);
    }
}

