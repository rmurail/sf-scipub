<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Publication;
use App\Entity\Science;
use App\Form\CommentType;
use App\Form\PublicationType;
use App\Repository\CommentRepository;
use App\Repository\PublicationRepository;
use App\Repository\ScienceRepository;
use App\Service\Notifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicationController extends AbstractController
{
    /**
     * Home action.
     *
     * @return Response
     *
     * @Route(
     *     "",
     *     name="publication_index",
     *     methods={"GET"}
     * )
     */
    public function index(): Response
    {
        $publications = $this
            ->getPublicationRepository()
            ->findLatest();

        return $this->render('Publication/index.html.twig', [
            'publications' => $publications,
        ]);
    }

    /**
     * Sciences list action.
     *
     * @return Response
     *
     * @Route(
     *     "/sciences",
     *     name="publication_sciences",
     *     methods={"GET"}
     * )
     */
    public function scienceList(): Response
    {
        return $this->render('Publication/science-list.html.twig', [
            'sciences' => $this->getScienceList(),
        ]);
    }

    /**
     * Science detail action.
     *
     * @param Request $request
     *
     * @return Response
     *
     * @Route(
     *     "/sciences/{scienceId}",
     *     name="publication_science",
     *     requirements={"scienceId": "\d+"},
     *     methods={"GET"}
     * )
     */
    public function scienceDetail(Request $request): Response
    {
        $science = $this->findScience($request);

        $publications = $this->getPublicationRepository()->findByScience($science);

        return $this->render('Publication/science-detail.html.twig', [
            'science'      => $science,
            'publications' => $publications,
        ]);
    }

    /**
     * Publication detail action.
     *
     * @param Request $request
     *
     * @return Response
     *
     * @Route(
     *     "/sciences/{scienceId}/{publicationId}",
     *     name="publication_publication",
     *     requirements={"scienceId": "\d+", "publicationId": "\d+"},
     *     methods={"GET", "POST"}
     * )
     */
    public function publicationDetail(Request $request): Response
    {
        $science = $this->findScience($request);
        $publication = $this->findPublication($request);

        if ($science !== $publication->getScience()) {
            throw $this->createNotFoundException();
        }

        $comment = new Comment();
        $comment->setPublication($publication);

        $form = $this->createForm(CommentType::class, $comment);

        $form->add('submit', SubmitType::class, [
            'label' => 'Publier',
            'attr'  => [
                'class' => 'btn-primary',
            ],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager = $this->getDoctrine()->getManager();
            $manager->persist($comment);
            $manager->flush();

            $this->addFlash(
                'success',
                'Votre commentaire a bien été enregistré et sera soumis ' .
                'à modération dans les plus brefs délais.'
            );

            return $this->redirectToRoute('publication_publication', [
                'scienceId'     => $science->getId(),
                'publicationId' => $publication->getId(),
            ]);
        }

        $comments = $this
            ->getCommentRepository()
            ->findByPublication($publication);

        return $this->render('Publication/publication-detail.html.twig', [
            'science'     => $science,
            'publication' => $publication,
            'form'        => $form->createView(),
            'comments'    => $comments,
        ]);
    }

    /**
     * Publish action.
     *
     * @param Request  $request
     * @param Notifier $notifier
     *
     * @return Response
     *
     * @Route(
     *     "/publish",
     *     name="publication_publish",
     *     methods={"GET", "POST"}
     * )
     */
    public function publish(Request $request, Notifier $notifier): Response
    {
        $publication = new Publication();

        $form = $this->createForm(PublicationType::class, $publication);

        $form->add('submit', SubmitType::class, [
            'label' => 'Publier',
            'attr'  => [
                'class' => 'btn-primary',
            ],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager = $this->getDoctrine()->getManager();
            $manager->persist($publication);
            $manager->flush();

            $notifier->notify($publication);

            $this->addFlash(
                'success',
                'Votre publication a bien été enregistrée et sera soumise ' .
                'à modération dans les plus brefs délais.'
            );

            return $this->redirectToRoute('publication_index');
        }

        return $this->render('Publication/publish.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Renders the side menu.
     *
     * @return Response
     */
    public function sideMenu(): Response
    {
        return $this->render('Fragment/sciences.html.twig', [
            'sciences' => $this->getScienceList(),
        ]);
    }

    /**
     * Finds the science from the given request.
     *
     * @param Request $request
     *
     * @return Science
     */
    private function findScience(Request $request): Science
    {
        $id = $request->attributes->getInt('scienceId');

        $science = $this->getScienceRepository()->find($id);

        if (!$science) {
            throw $this->createNotFoundException('Science not found.');
        }

        return $science;
    }

    /**
     * Finds the publication from the given request.
     *
     * @param Request $request
     *
     * @return Publication
     */
    private function findPublication(Request $request): Publication
    {
        $id = $request->attributes->getInt('publicationId');

        $publication = $this->getPublicationRepository()->find($id);

        if (!$publication) {
            throw $this->createNotFoundException('Publication not found.');
        }

        return $publication;
    }

    /**
     * Returns the science list.
     *
     * @return Science[]
     */
    private function getScienceList(): array
    {
        return $this->getScienceRepository()->findBy([], ['title' => 'ASC']);
    }

    /**
     * Returns the science repository.
     *
     * @return ScienceRepository
     */
    private function getScienceRepository(): ScienceRepository
    {
        return $this->getDoctrine()->getRepository(Science::class);
    }

    /**
     * Returns the publication repository.
     *
     * @return PublicationRepository
     */
    private function getPublicationRepository(): PublicationRepository
    {
        return $this->getDoctrine()->getRepository(Publication::class);
    }

    /**
     * Returns the comment repository.
     *
     * @return CommentRepository
     */
    private function getCommentRepository(): CommentRepository
    {
        return $this->getDoctrine()->getRepository(Comment::class);
    }
}
